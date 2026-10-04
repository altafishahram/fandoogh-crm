<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Marketplace\Services\PublicationService;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class PublicationAction
{
    public static function make(): Action
    {
        return Action::make('publication')->label('انتشار در بازار')->schema([
            Toggle::make('share_with_agencies')->label('همکاری بین آژانس‌ها'),
            Toggle::make('publish_public')->label('نمایش برای عموم'),
            TextInput::make('public_title')->label('عنوان آگهی')->required()->maxLength(200),
            Textarea::make('public_description')->label('توضیحات آگهی')->maxLength(5000)
                ->helperText('فقط اطلاعات قابل انتشار؛ نام مالک، شماره مالک و نشانی دقیق را ننویسید.'),
            CheckboxList::make('image_ids')->label('تصاویر قابل انتشار')
                ->options(fn (Property $record): array => $record->images()->orderBy('sort_order')->get()->mapWithKeys(fn ($image): array => [$image->id => 'تصویر '.($image->sort_order + 1)])->all()),
            Select::make('responding_user_id')->label('کارشناس پاسخ‌گو')->required()
                ->options(fn (Property $record): array => User::query()->where('agency_id', $record->agency_id)->where('is_active', true)
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', [RoleName::AgencyManager->value, RoleName::Agent->value]))->pluck('name', 'id')->all())
                ->disabled(fn (): bool => self::actor()->roleName() !== RoleName::AgencyManager)->dehydrated(),
            Hidden::make('expected_version'),
        ])->fillForm(function (Property $record): array {
            $publication = PropertyPublication::query()->where('property_id', $record->id)->first();

            return [
                'share_with_agencies' => $publication->share_with_agencies ?? false,
                'publish_public' => $publication->publish_public ?? false,
                'public_title' => $publication->public_title ?? '',
                'public_description' => $publication?->public_description,
                'responding_user_id' => $publication->responding_user_id ?? $record->created_by_user_id,
                'image_ids' => $publication?->images()->pluck('property_images.id')->all() ?? [],
                'expected_version' => $publication->version ?? 0,
            ];
        })->visible(fn (Property $record): bool => ! $record->trashed() && app(PublicationService::class)->canPublish(self::actor()))
            ->action(fn (Property $record, array $data) => app(PublicationService::class)->save(self::actor(), $record, $data));
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
