<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Property\Services\PropertyImageService;
use App\Application\Property\Services\PropertyPricingCalculator;
use App\Application\Property\Services\UpdatePropertyService;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\TransactionType;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Filament\Shared\Support\PersianDate;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

trait UpdatesProperty
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof Property) {
            $owner = $record->owners()->first();
            $data['owner'] = $owner === null ? [] : [
                'full_name' => $owner->full_name,
                'mobile' => $owner->mobile,
                'phone' => $owner->phone,
                'notes' => $owner->notes,
            ];
            $deliveryDate = $record->transaction_type === TransactionType::Sale
                && $record->delivery_status === DeliveryStatus::Ready
                ? $record->available_from
                : $record->evacuation_date;
            $data['evacuation_date_display'] = $deliveryDate === null
                ? null : PersianDate::format($deliveryDate);
            $data['sale_price_per_sqm'] = $record->sale_price !== null && $record->area_sqm !== null
                ? app(PropertyPricingCalculator::class)->perSquareMeter(
                    (string) $record->sale_price,
                    (string) $record->area_sqm,
                ) : null;
            $data['price_input_mode'] = 'total';
            $data['new_images'] = [];
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Property, 401);
        $images = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images']);
        $expected = CarbonImmutable::parse((string) $record->updated_at);
        $updated = app(UpdatePropertyService::class)->execute(
            $actor, $record, PanelDataMapper::propertyUpdate($data, $expected),
        );
        foreach ($images as $image) {
            if ($image instanceof UploadedFile && $updated->images()->count() < 5) {
                app(PropertyImageService::class)->upload($updated, $actor, $image);
            }
        }

        return $updated->refresh();
    }
}
