<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\EmbeddedOwnerData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Property\PropertyFeatureOptions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'province_id' => ['sometimes', 'nullable', 'integer'],
            'county_id' => ['sometimes', 'nullable', 'integer'],
            'city_id' => ['sometimes', 'nullable', 'integer'],

            'agency_id' => ['prohibited'], 'code' => ['prohibited'], 'currency_code' => ['prohibited'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)], 'created_by_user_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'building_type' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($this->input('property_type'), ['house', 'villa', 'house_villa'], true)),
                Rule::in(array_keys(PropertyFeatureOptions::BUILDING_TYPES)),
            ],
            'structure_type' => ['nullable', 'string', 'max:160'],
            'has_water' => ['sometimes', 'boolean'],
            'has_electricity' => ['sometimes', 'boolean'],
            'has_gas' => ['sometimes', 'boolean'],
            'telephone_line_count' => ['nullable', 'string', 'max:32'],
            'land_area' => ['nullable', 'string', 'max:64'],
            'building_area' => ['nullable', 'string', 'max:64'],
            'can_aggregate' => ['sometimes', 'boolean'],
            'land_frontage' => ['nullable', 'string', 'max:64'],
            'transaction_type' => ['required', Rule::enum(TransactionType::class)],
            'assigned_agent_id' => ['nullable', 'integer', 'min:1'],
            'sale_price' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('transaction_type') === TransactionType::Sale->value
                    && ! $this->filled('sale_price_per_sqm')),
                'decimal:0,2',
                'gt:0',
            ],
            'sale_price_per_sqm' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('transaction_type') === TransactionType::Sale->value
                    && ! $this->filled('sale_price')),
                'decimal:0,2',
                'gt:0',
            ],
            'price_input_mode' => ['nullable', Rule::in(['total', 'per_sqm'])],
            'deposit_amount' => ['nullable', 'required_if:transaction_type,rent', 'decimal:0,2', 'gte:0'],
            'monthly_rent' => ['nullable', 'required_if:transaction_type,rent', 'decimal:0,2', 'gte:0'],
            'is_convertible' => ['sometimes', 'boolean'],
            'minimum_deposit' => ['nullable', 'required_if:is_convertible,true', 'decimal:0,2', 'gte:0', 'lte:deposit_amount'],
            'area_sqm' => ['nullable', 'decimal:0,2', 'gt:0'],
            'bedrooms' => ['nullable', 'integer', 'between:0,255'],
            'bathrooms' => ['nullable', 'integer', 'between:0,255'],
            'floor_number' => ['nullable', 'integer', 'between:-32768,32767', 'lte:total_floors'],
            'total_floors' => ['nullable', 'integer', 'between:0,65535'],
            'year_built' => ['nullable', 'integer', 'between:1200,1600'],
            'parking_spaces' => ['sometimes', 'integer', 'between:0,255'],
            'has_storage_room' => ['sometimes', 'boolean'],
            'has_elevator' => ['sometimes', 'boolean'],
            'has_balcony' => ['sometimes', 'boolean'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'available_from' => ['nullable', 'date'],
            'plaque' => ['nullable', 'required_with:owner', 'string', 'max:32'],
            'units_per_floor' => ['nullable', 'integer', 'between:0,65535'],
            'master_bedrooms' => ['nullable', 'integer', 'between:0,255'],
            'toilet_types' => ['nullable', 'array', 'max:2'],
            'toilet_types.*' => ['string', Rule::in(array_keys(PropertyFeatureOptions::TOILET_TYPES)), 'distinct'],
            'has_master_bathroom' => ['sometimes', 'boolean'],
            'cabinet_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::CABINET_TYPES))],
            'heating_systems' => ['nullable', 'array'], 'heating_systems.*' => ['string', 'max:64', 'distinct'],
            'cooling_systems' => ['nullable', 'array'], 'cooling_systems.*' => ['string', 'max:64', 'distinct'],
            'heating_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::HEATING_TYPES))],
            'cooling_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::COOLING_TYPES))],
            'flooring_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::FLOORING_TYPES))],
            'renovation_status' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::RENOVATION_STATUSES))],
            'building_orientation' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::ORIENTATIONS))],
            'deed_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::DEED_TYPES))],
            'has_loan' => ['sometimes', 'boolean'],
            'is_exchangeable' => ['sometimes', 'boolean'],
            'has_pool' => ['sometimes', 'boolean'],
            'has_jacuzzi' => ['sometimes', 'boolean'],
            'has_sauna' => ['sometimes', 'boolean'],
            'delivery_status' => ['nullable', 'required_with:owner', Rule::enum(DeliveryStatus::class)],
            'evacuation_date' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('delivery_status'),
                    [DeliveryStatus::Dated->value, DeliveryStatus::TenantOccupied->value],
                    true,
                )),
            ],
            'owner' => ['nullable', 'required_without:owners', 'array:full_name,mobile,phone,notes'],
            'owner.full_name' => ['required_with:owner', 'string', 'max:200'],
            'owner.mobile' => ['required_with:owner', 'string', 'max:32'],
            'owner.phone' => ['nullable', 'string', 'max:32'],
            'owner.notes' => ['nullable', 'string', 'max:4000'],
            'owners' => [
                'nullable',
                'required_without:owner',
                'array',
                'size:1',
                Rule::prohibitedIf(fn (): bool => $this->filled('owner')),
            ],
            'owners.*.owner_id' => ['required', 'integer', 'min:1', 'distinct'],
            'owners.*.ownership_percentage' => ['nullable', 'decimal:0,2', 'between:0.01,100'],
            'owners.*.is_primary' => ['required', 'boolean'],
        ];
    }

    public function toData(): CreatePropertyData
    {
        $data = $this->validated();
        $owners = array_values(array_map(
            static fn (array $owner): PropertyOwnershipData => new PropertyOwnershipData(
                (int) $owner['owner_id'],
                isset($owner['ownership_percentage']) ? (string) $owner['ownership_percentage'] : null,
                (bool) $owner['is_primary'],
            ),
            $data['owners'] ?? [],
        ));
        $owner = isset($data['owner']) ? new EmbeddedOwnerData(
            (string) $data['owner']['full_name'],
            (string) $data['owner']['mobile'],
            $data['owner']['phone'] ?? null,
            $data['owner']['notes'] ?? null,
        ) : null;

        return new CreatePropertyData(
            $data['title'], $data['description'] ?? null,
            PropertyType::from($data['property_type']), TransactionType::from($data['transaction_type']),
            isset($data['assigned_agent_id']) ? (int) $data['assigned_agent_id'] : null,
            isset($data['sale_price']) ? (string) $data['sale_price'] : null,
            isset($data['deposit_amount']) ? (string) $data['deposit_amount'] : null,
            isset($data['monthly_rent']) ? (string) $data['monthly_rent'] : null,
            isset($data['area_sqm']) ? (string) $data['area_sqm'] : null,
            $data['bedrooms'] ?? null, $data['bathrooms'] ?? null,
            $data['floor_number'] ?? null, $data['total_floors'] ?? null, $data['year_built'] ?? null,
            $data['parking_spaces'] ?? 0, $data['has_storage_room'] ?? false,
            $data['has_elevator'] ?? false, $data['has_balcony'] ?? false,
            $data['city'], $data['district'] ?? null, $data['street_address'], $data['postal_code'] ?? null,
            isset($data['latitude']) ? (string) $data['latitude'] : null,
            isset($data['longitude']) ? (string) $data['longitude'] : null,
            isset($data['available_from']) ? CarbonImmutable::parse($data['available_from']) : null,
            $owners,
            $owner,
            isset($data['status']) ? PropertyStatus::from($data['status']) : null,
            $data['plaque'] ?? null, $data['units_per_floor'] ?? null, $data['master_bedrooms'] ?? null,
            $data['toilet_types'] ?? null, $data['cabinet_type'] ?? null,
            $data['heating_systems'] ?? null, $data['cooling_systems'] ?? null,
            $data['flooring_type'] ?? null, $data['renovation_status'] ?? null,
            isset($data['delivery_status']) ? DeliveryStatus::from($data['delivery_status']) : null,
            isset($data['evacuation_date']) ? CarbonImmutable::parse($data['evacuation_date']) : null,
            (bool) ($data['is_convertible'] ?? false),
            isset($data['minimum_deposit']) ? (string) $data['minimum_deposit'] : null,
            isset($data['sale_price_per_sqm']) ? (string) $data['sale_price_per_sqm'] : null,
            (string) ($data['price_input_mode'] ?? 'total'),
            (bool) ($data['has_master_bathroom'] ?? false),
            $data['heating_type'] ?? null, $data['cooling_type'] ?? null,
            $data['building_orientation'] ?? null, $data['deed_type'] ?? null,
            (bool) ($data['has_loan'] ?? false), (bool) ($data['is_exchangeable'] ?? false),
            (bool) ($data['has_pool'] ?? false), (bool) ($data['has_jacuzzi'] ?? false),
            (bool) ($data['has_sauna'] ?? false),
            $data['building_type'] ?? null, $data['structure_type'] ?? null,
            (bool) ($data['has_water'] ?? false), (bool) ($data['has_electricity'] ?? false),
            (bool) ($data['has_gas'] ?? false), $data['telephone_line_count'] ?? null,
            $data['land_area'] ?? null, $data['building_area'] ?? null,
            (bool) ($data['can_aggregate'] ?? false), $data['land_frontage'] ?? null,
            isset($data['province_id']) ? (int) $data['province_id'] : null,
            isset($data['county_id']) ? (int) $data['county_id'] : null,
            isset($data['city_id']) ? (int) $data['city_id'] : null,
        );
    }
}
