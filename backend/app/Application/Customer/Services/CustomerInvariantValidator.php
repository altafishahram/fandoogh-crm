<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\PropertyFeatureOptions;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;

final class CustomerInvariantValidator
{
    /** @param array<string, mixed> $attributes */
    public function validate(array $attributes): void
    {
        $types = $attributes['preferred_property_types'] ?? null;
        if ($types !== null) {
            if (! is_array($types) || count($types) !== count(array_unique($types))) {
                throw new DomainConflictException('انواع ملک ترجیحی باید یکتا باشند.');
            }
            foreach ($types as $type) {
                if (! is_string($type) || ! in_array($type, PropertyType::values(), true)) {
                    throw new DomainConflictException('یکی از انواع ملک ترجیحی پشتیبانی نمی‌شود.');
                }
            }
        }

        $this->orderedPair($attributes['budget_min'] ?? null, $attributes['budget_max'] ?? null, true);
        $this->orderedPair($attributes['min_area_sqm'] ?? null, $attributes['max_area_sqm'] ?? null, false);
        $this->orderedPair($attributes['land_area_min'] ?? null, $attributes['land_area_max'] ?? null, false);
        $this->orderedPair($attributes['building_area_min'] ?? null, $attributes['building_area_max'] ?? null, false);
        $this->orderedPair($attributes['rental_deposit_min'] ?? null, $attributes['rental_deposit_max'] ?? null, true);
        $this->orderedPair($attributes['rental_rent_min'] ?? null, $attributes['rental_rent_max'] ?? null, true);

        $minimumParking = $attributes['min_parking_spaces'] ?? null;
        if ($minimumParking !== null
            && (! is_numeric($minimumParking)
                || (float) (int) $minimumParking !== (float) $minimumParking
                || (int) $minimumParking < 0)) {
            throw new DomainConflictException('حداقل تعداد پارکینگ معتبر نیست.');
        }
        $ownerResides = (bool) ($attributes['owner_resides'] ?? false);

        $toiletTypes = $attributes['toilet_types'] ?? null;
        if ($toiletTypes !== null) {
            if (! is_array($toiletTypes) || count($toiletTypes) > 2 || count($toiletTypes) !== count(array_unique($toiletTypes))) {
                throw new DomainConflictException('نوع سرویس موردنظر معتبر نیست.');
            }
            foreach ($toiletTypes as $type) {
                if (! is_string($type) || ! array_key_exists($type, PropertyFeatureOptions::TOILET_TYPES)) {
                    throw new DomainConflictException('نوع سرویس موردنظر معتبر نیست.');
                }
            }
        }

        foreach ([
            'cabinet_type' => PropertyFeatureOptions::CABINET_TYPES,
            'heating_type' => PropertyFeatureOptions::HEATING_TYPES,
            'cooling_type' => PropertyFeatureOptions::COOLING_TYPES,
            'flooring_type' => PropertyFeatureOptions::FLOORING_TYPES,
            'renovation_status' => PropertyFeatureOptions::RENOVATION_STATUSES,
            'building_orientation' => PropertyFeatureOptions::ORIENTATIONS,
            'deed_type' => PropertyFeatureOptions::DEED_TYPES,
        ] as $field => $options) {
            $value = $attributes[$field] ?? null;
            if ($value !== null && (! is_string($value) || ! array_key_exists($value, $options))) {
                throw new DomainConflictException('یکی از ویژگی‌های موردنظر مشتری معتبر نیست.');
            }
        }

        $type = $attributes['desired_property_type'] ?? null;
        $intent = $attributes['intent'] ?? null;
        if ($intent !== null) {
            $intent = $intent instanceof CustomerIntent ? $intent : CustomerIntent::from((string) $intent);
        }
        if ($intent === CustomerIntent::Rent
            && (($attributes['has_loan'] ?? false)
                || ($attributes['is_exchangeable'] ?? false)
                || ($attributes['has_pool'] ?? false)
                || ($attributes['has_jacuzzi'] ?? false)
                || ($attributes['has_sauna'] ?? false)
                || ($attributes['deed_type'] ?? null) !== null
                || ($type !== PropertyType::Apartment->value
                    && ($attributes['has_master_bathroom'] ?? false)))) {
            throw new DomainConflictException('این ویژگی‌ها برای نیاز اجاره‌ای انتخاب‌شده مجاز نیستند.');
        }
        if ($intent !== CustomerIntent::Rent
            && ($minimumParking !== null
                || ($attributes['has_elevator'] ?? false)
                || ($attributes['has_balcony'] ?? false)
                || $ownerResides)) {
            throw new DomainConflictException('آسانسور، بالکن و سکونت مالک فقط برای نیاز اجاره‌ای مجاز هستند.');
        }
        if ($intent === CustomerIntent::Rent
            && in_array($type, [PropertyType::LandOldBuilding->value, PropertyType::Land->value], true)) {
            throw new DomainConflictException('ثبت نیاز اجاره برای زمین و ملک کلنگی مجاز نیست.');
        }
        $buildingType = $attributes['building_type'] ?? null;
        if ($buildingType !== null && ! array_key_exists((string) $buildingType, PropertyFeatureOptions::BUILDING_TYPES)) {
            throw new DomainConflictException('نوع بنای انتخاب‌شده معتبر نیست.');
        }
        if ($buildingType !== null && ! in_array($type, ['house', 'villa', 'house_villa'], true)) {
            throw new DomainConflictException('نوع بنا فقط برای خانه و ویلا مجاز است.');
        }
        if ($type !== PropertyType::Industrial->value
            && (($attributes['structure_type'] ?? null) !== null
                || ($attributes['telephone_line_count'] ?? null) !== null
                || ($attributes['land_area'] ?? null) !== null
                || ($attributes['building_area'] ?? null) !== null
                || ($attributes['land_area_min'] ?? null) !== null
                || ($attributes['land_area_max'] ?? null) !== null
                || ($attributes['building_area_min'] ?? null) !== null
                || ($attributes['building_area_max'] ?? null) !== null
                || ($attributes['has_water'] ?? false)
                || ($attributes['has_electricity'] ?? false)
                || ($attributes['has_gas'] ?? false))) {
            throw new DomainConflictException('مشخصات زیرساختی فقط برای نیاز صنعتی مجاز است.');
        }

        if (($attributes['full_name'] ?? null) !== null) {
            $isIndustrial = ($attributes['desired_property_type'] ?? null) === PropertyType::Industrial->value;
            if (($attributes['desired_property_type'] ?? null) === null) {
                throw new DomainConflictException('نوع ملک و بازه متراژ برای نیاز مشتری الزامی است.');
            }
            if ($isIndustrial) {
                $rangeFields = ['land_area_min', 'land_area_max', 'building_area_min', 'building_area_max'];
                $hasRange = array_reduce(
                    $rangeFields,
                    static fn (bool $complete, string $field): bool => $complete && ($attributes[$field] ?? null) !== null,
                    true,
                );
                $hasLegacyAreas = ($attributes['land_area'] ?? null) !== null
                    && ($attributes['building_area'] ?? null) !== null;
                if (! $hasRange && ! $hasLegacyAreas) {
                    throw new DomainConflictException('بازه متراژ زمین و بنا برای نیاز صنعتی الزامی است.');
                }
            } elseif (($attributes['min_area_sqm'] ?? null) === null
                || ($attributes['max_area_sqm'] ?? null) === null) {
                throw new DomainConflictException('نوع ملک و بازه متراژ برای نیاز مشتری الزامی است.');
            }
            if ($intent === CustomerIntent::Buy
                && (($attributes['budget_min'] ?? null) === null || ($attributes['budget_max'] ?? null) === null)) {
                throw new DomainConflictException('حداقل و حداکثر بودجه خرید الزامی است.');
            }
            if ($intent === CustomerIntent::Rent) {
                foreach (['rental_deposit_min', 'rental_deposit_max', 'rental_rent_min', 'rental_rent_max'] as $field) {
                    if (($attributes[$field] ?? null) === null) {
                        throw new DomainConflictException('بازه ودیعه و اجاره برای مشتری اجاره‌ای الزامی است.');
                    }
                }
            }
        }
    }

    public function conversion(Property $property, CustomerIntent $intent, User $actor): void
    {
        if ($property->transaction_type !== $intent->transactionType()) {
            throw new DomainConflictException('ملک نهایی‌شده با نوع درخواست مشتری سازگار نیست.');
        }

        if ($actor->roleName() === RoleName::Agent && $property->assigned_agent_id !== $actor->getKey()) {
            throw new DomainConflictException('این کارشناس به ملک نهایی‌شده دسترسی ندارد.');
        }
    }

    private function orderedPair(mixed $minimum, mixed $maximum, bool $allowZero): void
    {
        if (($minimum !== null && ! is_numeric($minimum)) || ($maximum !== null && ! is_numeric($maximum))) {
            throw new DomainConflictException('بازه عددی واردشده معتبر نیست.');
        }

        $minimumValue = $minimum === null ? null : (float) $minimum;
        $maximumValue = $maximum === null ? null : (float) $maximum;
        if (($minimumValue !== null && ($allowZero ? $minimumValue < 0 : $minimumValue <= 0))
            || ($maximumValue !== null && ($allowZero ? $maximumValue < 0 : $maximumValue <= 0))
            || ($minimumValue !== null && $maximumValue !== null && $minimumValue > $maximumValue)) {
            throw new DomainConflictException('بازه عددی واردشده معتبر نیست.');
        }
    }
}
