<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Shared\Services\OptimisticLock;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Exceptions\FileTooLargeException;
use App\Domain\Property\Exceptions\UnsupportedImageTypeException;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class PropertyImageService
{
    private const MAX_BYTES = 10_485_760;

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private PropertyRepositoryContract $properties,
        private PropertyHistoryWriter $history,
        private OptimisticLock $optimisticLock,
        private TenantContext $tenant,
    ) {}

    public function upload(Property $property, User $actor, UploadedFile $file): PropertyImage
    {
        $size = $file->getSize();
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new FileTooLargeException('حجم تصویر ملک نباید بیشتر از ۱۰ مگابایت باشد.');
        }

        $mime = $file->getMimeType();
        if (! is_string($mime) || ! array_key_exists($mime, self::EXTENSIONS)) {
            throw new UnsupportedImageTypeException('فقط تصاویر JPEG، PNG و WebP پذیرفته می‌شوند.');
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (! is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new UnsupportedImageTypeException('فایل بارگذاری‌شده تصویر قابل‌خواندن نیست.');
        }

        $directory = 'agencies/'.$this->tenant->agencyId().'/properties/'.$property->getKey();
        $filename = Str::uuid().'.'.self::EXTENSIONS[$mime];
        $path = $directory.'/'.$filename;

        if (! Storage::disk('local')->putFileAs($directory, $file, $filename)) {
            throw new DomainConflictException('ذخیره تصویر انجام نشد.');
        }

        try {
            return DB::transaction(function () use ($property, $actor, $file, $path, $mime, $size, $dimensions): PropertyImage {
                $this->properties->lock((int) $property->getKey());
                $images = PropertyImage::query()
                    ->where('property_id', $property->getKey())
                    ->lockForUpdate()
                    ->get();
                if ($images->count() >= 5) {
                    throw new DomainConflictException('هر ملک حداکثر می‌تواند ۵ تصویر فعال داشته باشد.');
                }

                $image = PropertyImage::query()->create([
                    'property_id' => $property->getKey(),
                    'uploaded_by_user_id' => $actor->getKey(),
                    'storage_path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                    'sort_order' => $images->count(),
                    'is_cover' => $images->isEmpty(),
                ]);
                $this->history->write($property, $actor, PropertyHistoryAction::ImagesChanged);

                return $image;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function update(
        Property $property,
        PropertyImage $image,
        User $actor,
        int $sortOrder,
        bool $isCover,
        CarbonImmutable $expectedUpdatedAt,
    ): PropertyImage {
        return DB::transaction(function () use ($property, $image, $actor, $sortOrder, $isCover, $expectedUpdatedAt): PropertyImage {
            $this->properties->lock((int) $property->getKey());
            /** @var PropertyImage $locked */
            $locked = PropertyImage::query()->lockForUpdate()->findOrFail($image->getKey());
            $this->optimisticLock->assertCurrent($locked, $expectedUpdatedAt);
            if ($isCover) {
                PropertyImage::query()
                    ->where('property_id', $property->getKey())
                    ->whereKeyNot($locked->getKey())
                    ->update(['is_cover' => false]);
            }

            $locked->sort_order = $sortOrder;
            $locked->is_cover = $isCover;
            $locked->save();

            $images = PropertyImage::query()
                ->where('property_id', $property->getKey())
                ->lockForUpdate()
                ->get();
            $images = $images->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])->values();
            foreach ($images as $index => $item) {
                $item->sort_order = (int) $index;
                $item->save();
            }

            if (! $images->contains('is_cover', true) && $images->isNotEmpty()) {
                $images->first()->update(['is_cover' => true]);
            }

            $this->history->write($property, $actor, PropertyHistoryAction::ImagesChanged);

            return $locked->refresh();
        });
    }

    public function delete(Property $property, PropertyImage $image, User $actor): void
    {
        DB::transaction(function () use ($property, $image, $actor): void {
            $this->properties->lock((int) $property->getKey());
            $wasCover = $image->is_cover;
            $image->delete();

            if ($wasCover) {
                PropertyImage::query()
                    ->where('property_id', $property->getKey())
                    ->orderBy('sort_order')
                    ->first()?->update(['is_cover' => true]);
            }
            $this->history->write($property, $actor, PropertyHistoryAction::ImagesChanged);
        });
    }
}
