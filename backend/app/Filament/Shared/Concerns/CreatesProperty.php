<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Property\Services\CreatePropertyService;
use App\Application\Property\Services\PropertyImageService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

trait CreatesProperty
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        $images = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images']);
        $property = app(CreatePropertyService::class)->execute($actor, PanelDataMapper::propertyCreate($data));
        foreach (array_slice($images, 0, 5) as $image) {
            if ($image instanceof UploadedFile) {
                app(PropertyImageService::class)->upload($property, $actor, $image);
            }
        }

        return $property;
    }
}
