<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Owner\Data\OwnerData;
use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Property\Data\UpdatePropertyData;
use App\Application\SavedFilter\Data\SavedFilterData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Owner\Enums\OwnerType;
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
        return new CreatePropertyData(
            (string) $data['title'], self::string($data, 'description'),
            PropertyType::from((string) $data['property_type']),
            TransactionType::from((string) $data['transaction_type']),
            isset($data['assigned_agent_id']) ? (int) $data['assigned_agent_id'] : null,
            self::string($data, 'sale_price'), self::string($data, 'deposit_amount'),
            self::string($data, 'monthly_rent'), self::string($data, 'area_sqm'),
            self::integer($data, 'bedrooms'), self::integer($data, 'bathrooms'),
            self::integer($data, 'floor_number'), self::integer($data, 'total_floors'),
            self::integer($data, 'year_built'), (int) ($data['parking_spaces'] ?? 0),
            (bool) ($data['has_storage_room'] ?? false), (bool) ($data['has_elevator'] ?? false),
            (bool) ($data['has_balcony'] ?? false), (string) $data['city'], self::string($data, 'district'),
            (string) $data['street_address'], self::string($data, 'postal_code'),
            self::string($data, 'latitude'), self::string($data, 'longitude'),
            self::date($data, 'available_from'), self::ownerships($data),
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
            'longitude', 'available_from',
        ];

        return new UpdatePropertyData(array_intersect_key($data, array_flip($allowed)), $expected);
    }

    /** @param array<string, mixed> $data */
    public static function customerCreate(array $data): CreateCustomerData
    {
        return new CreateCustomerData(
            (int) $data['assigned_agent_id'], (string) $data['first_name'], (string) $data['last_name'],
            (string) $data['mobile'], self::string($data, 'phone'), self::string($data, 'email'),
            PreferredContactMethod::from((string) $data['preferred_contact_method']),
            CustomerIntent::from((string) $data['intent']),
            isset($data['preferred_property_types']) && is_array($data['preferred_property_types'])
                ? array_values(array_map('strval', $data['preferred_property_types'])) : null,
            self::string($data, 'budget_min'), self::string($data, 'budget_max'),
            self::string($data, 'desired_city'), self::string($data, 'desired_district'),
            self::string($data, 'min_area_sqm'), self::string($data, 'max_area_sqm'),
            self::integer($data, 'min_bedrooms'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function customerUpdate(array $data, CarbonImmutable $expected): UpdateCustomerData
    {
        $allowed = [
            'assigned_agent_id', 'first_name', 'last_name', 'mobile', 'phone', 'email',
            'preferred_contact_method', 'intent', 'status', 'preferred_property_types', 'budget_min',
            'budget_max', 'desired_city', 'desired_district', 'min_area_sqm', 'max_area_sqm',
            'min_bedrooms', 'converted_property_id',
        ];

        return new UpdateCustomerData(array_intersect_key($data, array_flip($allowed)), $expected);
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

    /** @param array<string, mixed> $data */
    private static function date(array $data, string $key): ?CarbonImmutable
    {
        return isset($data[$key]) && $data[$key] !== ''
            ? CarbonImmutable::parse((string) $data[$key]) : null;
    }
}
