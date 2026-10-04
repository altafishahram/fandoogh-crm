<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Property\PropertyFeatureOptions;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;

final class PropertyInvariantValidator
{
    /** @param list<PropertyOwnershipData> $ownerships */
    public function ownerships(array $ownerships): void
    {
        if (count($ownerships) !== 1) {
            throw new DomainConflictException('هر ملک باید دقیقاً یک مالک داشته باشد.');
        }

        $ownerIds = array_map(static fn (PropertyOwnershipData $item): int => $item->ownerId, $ownerships);

        if (count($ownerIds) !== count(array_unique($ownerIds))) {
            throw new DomainConflictException('هر مالک فقط یک‌بار می‌تواند به ملک متصل شود.');
        }

        if (! $ownerships[0]->isPrimary) {
            throw new DomainConflictException('دقیقاً یک مالک باید به‌عنوان مالک اصلی مشخص شود.');
        }

        $knownShares = array_filter(
            $ownerships,
            static fn (PropertyOwnershipData $item): bool => $item->ownershipPercentage !== null,
        );

        if ($knownShares !== [] && count($knownShares) !== count($ownerships)) {
            throw new DomainConflictException('درصد مالکیت باید برای همه مالکان وارد شود یا برای هیچ‌کدام وارد نشود.');
        }

        if ($knownShares !== []) {
            $sumCents = array_sum(array_map(
                static fn (PropertyOwnershipData $item): int => (int) round(((float) $item->ownershipPercentage) * 100),
                $ownerships,
            ));

            if ($sumCents !== 10_000) {
                throw new DomainConflictException('مجموع درصد مالکیت باید دقیقاً ۱۰۰ باشد.');
            }
        }

        if (Owner::query()->whereKey($ownerIds)->count() !== count($ownerIds)) {
            throw new DomainConflictException('همه مالکان باید متعلق به آژانس جاری باشند.');
        }

        if (Owner::query()->whereKey($ownerIds)->whereHas('properties')->exists()) {
            throw new DomainConflictException('اطلاعات مالک هر ملک باید مستقل ثبت شود و قابل استفاده دوباره نیست.');
        }
    }

    /** @param array<string, mixed> $attributes */
    public function commercial(array $attributes): void
    {
        $transaction = $attributes['transaction_type'];
        $transaction = $transaction instanceof TransactionType ? $transaction : TransactionType::from((string) $transaction);
        $salePrice = $attributes['sale_price'] ?? null;
        $deposit = $attributes['deposit_amount'] ?? null;
        $rent = $attributes['monthly_rent'] ?? null;
        $delivery = $attributes['delivery_status'] ?? null;
        $delivery = $delivery instanceof DeliveryStatus ? $delivery->value : $delivery;
        $tenantOccupiedSale = $transaction === TransactionType::Sale
            && $delivery === DeliveryStatus::TenantOccupied->value;

        if ($transaction === TransactionType::Sale) {
            if (! $this->positive($salePrice)
                || (! $tenantOccupiedSale && ($deposit !== null || $rent !== null))
                || ($tenantOccupiedSale
                    && (($deposit !== null && ! $this->nonNegative($deposit))
                        || ($rent !== null && ! $this->nonNegative($rent))))) {
                throw new DomainConflictException('ملک فروشی به قیمت فروش مثبت نیاز دارد و نباید مبلغ اجاره داشته باشد.');
            }
        } elseif ($salePrice !== null || ! $this->nonNegative($rent) || ! $this->nonNegative($deposit)) {
            throw new DomainConflictException('ملک اجاره‌ای به اجاره ماهانه و ودیعه نامنفی نیاز دارد.');
        }

        $convertible = (bool) ($attributes['is_convertible'] ?? false);
        $minimumDeposit = $attributes['minimum_deposit'] ?? null;
        if ($convertible && ($transaction !== TransactionType::Rent || ! $this->nonNegative($minimumDeposit)
            || (float) $minimumDeposit > (float) $deposit)) {
            throw new DomainConflictException('بازه تبدیل ودیعه و اجاره معتبر نیست.');
        }
        if (! $convertible && $minimumDeposit !== null) {
            throw new DomainConflictException('حداقل ودیعه فقط برای ملک قابل تبدیل ثبت می‌شود.');
        }

        $evacuationDate = $attributes['evacuation_date'] ?? null;
        if (in_array($delivery, ['dated', 'tenant_occupied'], true) && $evacuationDate === null) {
            throw new DomainConflictException('تاریخ تخلیه برای این وضعیت الزامی است.');
        }
        if ($delivery !== null && ! in_array($delivery, ['dated', 'tenant_occupied'], true) && $evacuationDate !== null) {
            throw new DomainConflictException('تاریخ تخلیه فقط برای سکونت مستأجر یا وضعیت دارای تاریخ ثبت می‌شود.');
        }
        if ($transaction === TransactionType::Rent && in_array($delivery, ['owner_occupied', 'tenant_occupied'], true)) {
            throw new DomainConflictException('وضعیت سکونت مالک یا مستأجر فقط برای ملک فروشی مجاز است.');
        }

        $type = $attributes['property_type'] ?? null;
        $type = $type instanceof PropertyType ? $type->value : $type;
        if ($this->isLandType($type) && $transaction !== TransactionType::Sale) {
            throw new DomainConflictException('ثبت اجاره برای زمین و ملک کلنگی مجاز نیست.');
        }
        if ($transaction === TransactionType::Rent
            && (($attributes['year_built'] ?? null) !== null
                || (bool) ($attributes['has_loan'] ?? false)
                || (bool) ($attributes['is_exchangeable'] ?? false))) {
            throw new DomainConflictException('سال ساخت، وام و قابلیت معاوضه برای ملک اجاره‌ای مجاز نیست.');
        }
        $this->typeSpecificFields($attributes, $type);
        if ($this->isLandType($type)) {
            foreach ([
                'bedrooms', 'bathrooms', 'master_bedrooms', 'floor_number', 'total_floors', 'units_per_floor',
                'year_built', 'parking_spaces', 'has_storage_room', 'has_elevator', 'has_balcony', 'toilet_types',
                'has_master_bathroom', 'cabinet_type', 'heating_systems', 'cooling_systems', 'heating_type',
                'cooling_type', 'flooring_type', 'renovation_status', 'building_orientation', 'has_pool',
                'has_jacuzzi', 'has_sauna', 'building_type', 'structure_type', 'has_water', 'has_electricity',
                'has_gas', 'telephone_line_count', 'land_area', 'building_area',
            ] as $field) {
                if ($this->hasMeaningfulValue($attributes[$field] ?? null)) {
                    throw new DomainConflictException('برای زمین و ملک کلنگی فقط نوع سند، وام، قابلیت معاوضه، قابلیت تجمیع و حد زمین مجاز است.');
                }
            }
        }

        $year = $attributes['year_built'] ?? null;
        if ($year !== null && ((int) $year < 1200 || (int) $year > 1600)) {
            throw new DomainConflictException('سال ساخت خارج از بازه مجاز است.');
        }

        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;
        if (($latitude === null) !== ($longitude === null)) {
            throw new DomainConflictException('عرض و طول جغرافیایی باید باهم وارد شوند.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private function typeSpecificFields(array $attributes, mixed $type): void
    {
        $houseTypes = ['house', 'villa', 'house_villa'];
        $buildingType = $attributes['building_type'] ?? null;
        if ($buildingType !== null && ! array_key_exists((string) $buildingType, PropertyFeatureOptions::BUILDING_TYPES)) {
            throw new DomainConflictException('نوع بنای انتخاب‌شده معتبر نیست.');
        }
        if ($buildingType !== null && ! in_array($type, $houseTypes, true)) {
            throw new DomainConflictException('نوع بنا فقط برای خانه و ویلا مجاز است.');
        }

        if ($type !== PropertyType::Industrial->value
            && (($attributes['structure_type'] ?? null) !== null
                || ($attributes['telephone_line_count'] ?? null) !== null
                || ($attributes['land_area'] ?? null) !== null
                || ($attributes['building_area'] ?? null) !== null
                || ($attributes['has_water'] ?? false)
                || ($attributes['has_electricity'] ?? false)
                || ($attributes['has_gas'] ?? false))) {
            throw new DomainConflictException('مشخصات زیرساختی فقط برای ملک صنعتی مجاز است.');
        }

        if (! $this->isLandType($type)
            && (($attributes['can_aggregate'] ?? false)
                || ($attributes['land_frontage'] ?? null) !== null)) {
            throw new DomainConflictException('قابلیت تجمیع و حد زمین فقط برای زمین و ملک کلنگی مجاز است.');
        }
    }

    private function isLandType(mixed $type): bool
    {
        return in_array($type, [PropertyType::LandOldBuilding->value, PropertyType::Land->value], true);
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            return $value !== [];
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return $value !== null && $value !== '';
    }

    private function positive(mixed $value): bool
    {
        return $value !== null && (float) $value > 0;
    }

    private function nonNegative(mixed $value): bool
    {
        return $value !== null && (float) $value >= 0;
    }
}
