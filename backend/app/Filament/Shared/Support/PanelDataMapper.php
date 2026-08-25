<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Owner\Data\OwnerData;
use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\EmbeddedOwnerData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Property\Data\UpdatePropertyData;
use App\Application\SavedFilter\Data\SavedFilterData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\SavedFilter\Enums\FilterModule;
use Carbon\CarbonImmutable;

final class PanelDataMapper
{
    /** @param array<string, mixed> $data */
    public static function owner(array $data): OwnerData
    {
        return new OwnerData(
            OwnerType::from((string) $data['owner_type']), self::string($data, 'first_name'),
            self::string($data, 'last_name'), self::string($data, 'company_name'),
            self::string($data, 'mobile'), self::string($data, 'phone'), self::string($data, 'email'),
            self::string($data, 'identity_number_encrypted'), self::string($data, 'address_line_1'),
            self::string($data, 'address_line_2'), self::string($data, 'city'),
            self::string($data, 'province'), self::string($data, 'postal_code'), self::string($data, 'notes'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function propertyCreate(array $data): CreatePropertyData
    {
        $transaction = TransactionType::from((string) $data['transaction_type']);
        $type = PropertyType::from((string) $data['property_type']);
        $owner = is_array($data['owner'] ?? null) ? $data['owner'] : [];
        $isLand = $type === PropertyType::LandOldBuilding;
        $isRent = $transaction === TransactionType::Rent;
        $building = $isLand ? [] : $data;
        $delivery = DeliveryStatus::from((string) $data['delivery_status']);
        $deliveryDate = self::persianDate($data, 'evacuation_date_display');
        $availableFrom = $transaction === TransactionType::Sale && $delivery === DeliveryStatus::Ready
            ? $deliveryDate : null;
        $evacuationDate = in_array($delivery, [
            DeliveryStatus::Dated,
            DeliveryStatus::TenantOccupied,
        ], true) ? $deliveryDate : null;

        return new CreatePropertyData(
            (string) $data['title'], self::string($data, 'description'),
            $type, $transaction,
            isset($data['assigned_agent_id']) ? (int) $data['assigned_agent_id'] : null,
            $transaction === TransactionType::Sale ? self::string($data, 'sale_price') : null,
            ($isRent || $delivery === DeliveryStatus::TenantOccupied)
                ? self::string($data, 'deposit_amount') : null,
            ($isRent || $delivery === DeliveryStatus::TenantOccupied)
                ? self::string($data, 'monthly_rent') : null,
            self::string($data, 'area_sqm'),
            self::integer($building, 'bedrooms'), self::integer($building, 'bathrooms'),
            self::integer($building, 'floor_number'), self::integer($building, 'total_floors'),
            $isRent ? null : self::integer($building, 'year_built'), (int) ($building['parking_spaces'] ?? 0),
            (bool) ($building['has_storage_room'] ?? false), (bool) ($building['has_elevator'] ?? false),
            (bool) ($building['has_balcony'] ?? false), self::string($data, 'city'), self::string($data, 'district'),
            (string) $data['street_address'], self::string($data, 'postal_code'),
            null, null, $availableFrom, [],
            new EmbeddedOwnerData(
                (string) ($owner['full_name'] ?? ''), (string) ($owner['mobile'] ?? ''),
                self::string($owner, 'phone'), self::string($owner, 'notes'),
            ),
            isset($data['status']) ? PropertyStatus::from((string) $data['status']) : null,
            self::string($data, 'plaque'), self::integer($building, 'units_per_floor'),
            self::integer($building, 'master_bedrooms'), self::stringList($data, 'toilet_types'),
            self::string($data, 'cabinet_type'), null,
            null, self::string($data, 'flooring_type'),
            self::string($data, 'renovation_status'), $delivery,
            $evacuationDate,
            $transaction === TransactionType::Rent && (bool) ($data['is_convertible'] ?? false),
            $transaction === TransactionType::Rent && (bool) ($data['is_convertible'] ?? false)
                ? self::string($data, 'minimum_deposit') : null,
            $transaction === TransactionType::Sale ? self::string($data, 'sale_price_per_sqm') : null,
            (string) ($data['price_input_mode'] ?? 'total'),
            (bool) ($data['has_master_bathroom'] ?? false),
            self::string($data, 'heating_type'), self::string($data, 'cooling_type'),
            self::string($data, 'building_orientation'), self::string($data, 'deed_type'),
            ! $isRent && (bool) ($data['has_loan'] ?? false), ! $isRent && (bool) ($data['is_exchangeable'] ?? false),
            (bool) ($data['has_pool'] ?? false), (bool) ($data['has_jacuzzi'] ?? false),
            (bool) ($data['has_sauna'] ?? false),
            self::string($data, 'building_type'), self::string($data, 'structure_type'),
            (bool) ($data['has_water'] ?? false), (bool) ($data['has_electricity'] ?? false),
            (bool) ($data['has_gas'] ?? false), self::string($data, 'telephone_line_count'),
            self::string($data, 'land_area'), self::string($data, 'building_area'),
            (bool) ($data['can_aggregate'] ?? false), self::string($data, 'land_frontage'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function propertyUpdate(array $data, CarbonImmutable $expected): UpdatePropertyData
    {
        $allowed = [
            'title', 'description', 'property_type', 'transaction_type', 'assigned_agent_id', 'sale_price',
            'deposit_amount', 'monthly_rent', 'area_sqm', 'bedrooms', 'bathrooms', 'floor_number',
            'total_floors', 'year_built', 'parking_spaces', 'has_storage_room', 'has_elevator',
            'has_balcony', 'city', 'district', 'street_address', 'postal_code', 'latitude',
            'longitude', 'available_from', 'plaque', 'units_per_floor', 'master_bedrooms',
            'toilet_types', 'cabinet_type', 'heating_systems', 'cooling_systems', 'flooring_type',
            'renovation_status', 'delivery_status', 'evacuation_date', 'is_convertible', 'minimum_deposit',
            'has_master_bathroom', 'heating_type', 'cooling_type', 'building_orientation', 'deed_type',
            'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
            'telephone_line_count', 'land_area', 'building_area', 'can_aggregate', 'land_frontage',
        ];
        $transaction = TransactionType::from((string) $data['transaction_type']);
        $type = PropertyType::from((string) $data['property_type']);
        $attributes = array_intersect_key($data, array_flip($allowed));
        $attributes['property_type'] = $type;
        $attributes['transaction_type'] = $transaction;
        $delivery = DeliveryStatus::from((string) $data['delivery_status']);
        $deliveryDate = self::persianDate($data, 'evacuation_date_display');
        if ($transaction === TransactionType::Sale) {
            $attributes['available_from'] = $delivery === DeliveryStatus::Ready ? $deliveryDate : null;
        }
        $attributes['evacuation_date'] = in_array($delivery, [
            DeliveryStatus::Dated,
            DeliveryStatus::TenantOccupied,
        ], true) ? $deliveryDate : null;
        if ($transaction === TransactionType::Sale) {
            $tenantSale = $delivery === DeliveryStatus::TenantOccupied;
            $attributes['deposit_amount'] = $tenantSale
                ? self::string($data, 'deposit_amount') : null;
            $attributes['monthly_rent'] = $tenantSale
                ? self::string($data, 'monthly_rent') : null;
            $attributes['is_convertible'] = false;
            $attributes['minimum_deposit'] = null;
        } else {
            $attributes['sale_price'] = null;
            $attributes['year_built'] = null;
            $attributes['has_loan'] = false;
            $attributes['is_exchangeable'] = false;
        }
        if ($type === PropertyType::LandOldBuilding) {
            $attributes['transaction_type'] = TransactionType::Sale;
            $attributes['deposit_amount'] = null;
            $attributes['monthly_rent'] = null;
            $attributes['is_convertible'] = false;
            $attributes['minimum_deposit'] = null;
            foreach (self::buildingFields() as $field) {
                $attributes[$field] = null;
            }
            foreach (['has_storage_room', 'has_elevator', 'has_balcony'] as $field) {
                $attributes[$field] = false;
            }
            $attributes['parking_spaces'] = 0;
            foreach (['toilet_types', 'cabinet_type', 'heating_systems', 'cooling_systems', 'flooring_type',
                'renovation_status', 'building_orientation', 'has_master_bathroom', 'has_pool', 'has_jacuzzi',
                'has_sauna', 'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
                'telephone_line_count', 'land_area', 'building_area'] as $field) {
                $attributes[$field] = is_bool($attributes[$field] ?? null) ? false : null;
            }
        } else {
            $attributes['can_aggregate'] = false;
            $attributes['land_frontage'] = null;
        }
        $owner = is_array($data['owner'] ?? null) ? $data['owner'] : [];

        return new UpdatePropertyData(
            $attributes,
            $expected,
            isset($data['lock_version']) ? (int) $data['lock_version'] : null,
            new EmbeddedOwnerData(
                (string) ($owner['full_name'] ?? ''), (string) ($owner['mobile'] ?? ''),
                self::string($owner, 'phone'), self::string($owner, 'notes'),
            ),
            self::string($data, 'sale_price_per_sqm'),
            (string) ($data['price_input_mode'] ?? 'total'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function customerCreate(array $data): CreateCustomerData
    {
        $intent = CustomerIntent::from((string) $data['intent']);
        $isRent = $intent === CustomerIntent::Rent;
        $isApartment = (string) ($data['desired_property_type'] ?? '') === PropertyType::Apartment->value;
        $hasParking = array_key_exists('has_parking', $data)
            ? (bool) $data['has_parking']
            : (int) ($data['min_parking_spaces'] ?? 0) > 0;

        return new CreateCustomerData(
            null, (string) $data['full_name'], '',
            (string) $data['mobile'], self::string($data, 'phone'), null,
            PreferredContactMethod::Phone, $intent, [(string) $data['desired_property_type']],
            $intent === CustomerIntent::Buy ? self::string($data, 'budget_min') : null,
            $intent === CustomerIntent::Buy ? self::string($data, 'budget_max') : null,
            self::string($data, 'desired_city'), self::string($data, 'desired_district'),
            self::string($data, 'min_area_sqm'), self::string($data, 'max_area_sqm'),
            self::integer($data, 'min_bedrooms'),
            (string) $data['full_name'], (string) $data['desired_property_type'],
            $intent === CustomerIntent::Rent ? self::string($data, 'rental_deposit_min') : null,
            $intent === CustomerIntent::Rent ? self::string($data, 'rental_deposit_max') : null,
            $intent === CustomerIntent::Rent ? self::string($data, 'rental_rent_min') : null,
            $intent === CustomerIntent::Rent ? self::string($data, 'rental_rent_max') : null,
            $intent !== CustomerIntent::Rent || (bool) ($data['accepts_rent_conversion'] ?? true),
            self::string($data, 'description'),
            is_array($data['toilet_types'] ?? null) ? array_values($data['toilet_types']) : null,
            ! $isRent || $isApartment ? (bool) ($data['has_master_bathroom'] ?? false) : false,
            self::string($data, 'cabinet_type'), self::string($data, 'heating_type'),
            self::string($data, 'cooling_type'), self::string($data, 'flooring_type'),
            self::string($data, 'renovation_status'), self::string($data, 'building_orientation'),
            ! $isRent ? self::string($data, 'deed_type') : null,
            ! $isRent && (bool) ($data['has_loan'] ?? false),
            ! $isRent && (bool) ($data['is_exchangeable'] ?? false),
            ! $isRent && (bool) ($data['has_pool'] ?? false),
            ! $isRent && (bool) ($data['has_jacuzzi'] ?? false),
            ! $isRent && (bool) ($data['has_sauna'] ?? false),
            self::string($data, 'building_type'), self::string($data, 'structure_type'),
            (bool) ($data['has_water'] ?? false), (bool) ($data['has_electricity'] ?? false),
            (bool) ($data['has_gas'] ?? false), self::string($data, 'telephone_line_count'),
            self::string($data, 'land_area'), self::string($data, 'building_area'),
            $isRent && $hasParking ? 1 : null,
            $isRent && (bool) ($data['has_elevator'] ?? false),
            $isRent && (bool) ($data['has_balcony'] ?? false),
            $hasParking,
            (bool) ($data['has_storage_room'] ?? false),
            $isRent && (bool) ($data['owner_resides'] ?? false),
        );
    }

    /** @param array<string, mixed> $data */
    public static function customerUpdate(array $data, CarbonImmutable $expected): UpdateCustomerData
    {
        $allowed = [
            'assigned_agent_id', 'first_name', 'last_name', 'mobile', 'phone', 'email',
            'preferred_contact_method', 'intent', 'status', 'preferred_property_types', 'budget_min',
            'budget_max', 'desired_city', 'desired_district', 'min_area_sqm', 'max_area_sqm',
            'min_bedrooms', 'converted_property_id', 'full_name', 'desired_property_type',
            'rental_deposit_min', 'rental_deposit_max', 'rental_rent_min', 'rental_rent_max',
            'accepts_rent_conversion', 'description',
            'toilet_types', 'has_master_bathroom', 'cabinet_type', 'heating_type', 'cooling_type',
            'flooring_type', 'renovation_status', 'building_orientation', 'deed_type', 'has_loan',
            'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
            'telephone_line_count', 'land_area', 'building_area', 'min_parking_spaces',
            'has_parking', 'has_storage_room', 'owner_resides', 'has_elevator', 'has_balcony',
        ];
        $attributes = array_intersect_key($data, array_flip($allowed));
        $attributes['first_name'] = (string) $data['full_name'];
        $attributes['last_name'] = '';
        $attributes['email'] = null;
        $attributes['preferred_contact_method'] = PreferredContactMethod::Phone;
        $attributes['preferred_property_types'] = [(string) $data['desired_property_type']];
        if (($data['desired_property_type'] ?? null) === PropertyType::LandOldBuilding->value) {
            $attributes['intent'] = CustomerIntent::Buy;
            foreach (['rental_deposit_min', 'rental_deposit_max', 'rental_rent_min', 'rental_rent_max'] as $field) {
                $attributes[$field] = null;
            }
        }
        if (($data['intent'] ?? null) === CustomerIntent::Buy->value) {
            foreach (['rental_deposit_min', 'rental_deposit_max', 'rental_rent_min', 'rental_rent_max'] as $field) {
                $attributes[$field] = null;
            }
        } else {
            $attributes['budget_min'] = null;
            $attributes['budget_max'] = null;
        }
        if (array_key_exists('has_parking', $attributes)) {
            $attributes['min_parking_spaces'] = (bool) $attributes['has_parking'] ? 1 : null;
        } elseif (array_key_exists('min_parking_spaces', $attributes)) {
            $attributes['has_parking'] = (int) $attributes['min_parking_spaces'] > 0;
        }
        if (($attributes['intent'] ?? null) === CustomerIntent::Rent->value) {
            $attributes['has_loan'] = false;
            $attributes['is_exchangeable'] = false;
            $attributes['has_pool'] = false;
            $attributes['has_jacuzzi'] = false;
            $attributes['has_sauna'] = false;
            $attributes['deed_type'] = null;
            if (($attributes['desired_property_type'] ?? null) !== PropertyType::Apartment->value) {
                $attributes['has_master_bathroom'] = false;
            }
        } else {
            $attributes['min_parking_spaces'] = null;
            $attributes['owner_resides'] = false;
            $attributes['has_elevator'] = false;
            $attributes['has_balcony'] = false;
        }

        return new UpdateCustomerData(
            $attributes,
            $expected,
            isset($data['lock_version']) ? (int) $data['lock_version'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    public static function savedFilter(array $data): SavedFilterData
    {
        $filters = $data['filters'] ?? [];

        return new SavedFilterData(
            FilterModule::from((string) $data['module']), (string) $data['name'],
            is_array($filters) ? $filters : [], self::string($data, 'sort'), (bool) ($data['is_default'] ?? false),
        );
    }

    /** @param array<string, mixed> $data
     * @return list<PropertyOwnershipData>
     */
    public static function ownerships(array $data): array
    {
        $rows = $data['owners'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $result[] = new PropertyOwnershipData(
                (int) $row['owner_id'], self::string($row, 'ownership_percentage'),
                (bool) ($row['is_primary'] ?? false),
            );
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): ?string
    {
        if (! isset($data[$key]) || trim((string) $data[$key]) === '') {
            return null;
        }

        return trim((string) $data[$key]);
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key): ?int
    {
        return isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
    }

    /** @param array<string, mixed> $data
     * @return list<string>|null
     */
    private static function stringList(array $data, string $key): ?array
    {
        return isset($data[$key]) && is_array($data[$key])
            ? array_values(array_map('strval', $data[$key])) : null;
    }

    /** @param array<string, mixed> $data */
    private static function persianDate(array $data, string $key): ?CarbonImmutable
    {
        return isset($data[$key]) && trim((string) $data[$key]) !== ''
            ? PersianDate::parse((string) $data[$key]) : null;
    }

    /** @return list<string> */
    private static function buildingFields(): array
    {
        return [
            'bedrooms', 'bathrooms', 'master_bedrooms', 'floor_number',
            'total_floors', 'units_per_floor', 'year_built',
        ];
    }
}
