<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use App\Application\Property\Data\EmbeddedOwnerData;
use App\Application\Property\Data\UpdatePropertyData;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Property\PropertyFeatureOptions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePropertyRequest extends FormRequest
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
            'status' => ['prohibited'], 'created_by_user_id' => ['prohibited'], 'owners' => ['prohibited'],
            'expected_updated_at' => ['required_without:expected_version', 'date'],
            'expected_version' => ['required_without:expected_updated_at', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'property_type' => ['sometimes', Rule::enum(PropertyType::class)],
            'building_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::BUILDING_TYPES))],
            'structure_type' => ['sometimes', 'nullable', 'string', 'max:160'],
            'has_water' => ['sometimes', 'boolean'],
            'has_electricity' => ['sometimes', 'boolean'],
            'has_gas' => ['sometimes', 'boolean'],
            'telephone_line_count' => ['sometimes', 'nullable', 'string', 'max:32'],
            'land_area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'building_area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'can_aggregate' => ['sometimes', 'boolean'],
            'land_frontage' => ['sometimes', 'nullable', 'string', 'max:64'],
            'transaction_type' => ['sometimes', Rule::enum(TransactionType::class)],
            'assigned_agent_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sale_price' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'deposit_amount' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'monthly_rent' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'sale_price_per_sqm' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'price_input_mode' => ['sometimes', Rule::in(['total', 'per_sqm'])],
            'is_convertible' => ['sometimes', 'boolean'],
            'minimum_deposit' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'area_sqm' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'floor_number' => ['sometimes', 'nullable', 'integer', 'between:-32768,32767'],
            'total_floors' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
            'year_built' => ['sometimes', 'nullable', 'integer', 'between:1200,1600'],
            'parking_spaces' => ['sometimes', 'integer', 'between:0,255'],
            'has_storage_room' => ['sometimes', 'boolean'],
            'has_elevator' => ['sometimes', 'boolean'],
            'has_balcony' => ['sometimes', 'boolean'],
            'city' => ['sometimes', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'street_address' => ['sometimes', 'string', 'max:500'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'plaque' => ['sometimes', 'nullable', 'string', 'max:32'],
            'units_per_floor' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
            'master_bedrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'toilet_types' => ['sometimes', 'nullable', 'array', 'max:2'],
            'toilet_types.*' => ['string', Rule::in(array_keys(PropertyFeatureOptions::TOILET_TYPES)), 'distinct'],
            'has_master_bathroom' => ['sometimes', 'boolean'],
            'cabinet_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::CABINET_TYPES))],
            'heating_systems' => ['sometimes', 'nullable', 'array'], 'heating_systems.*' => ['string', 'max:64', 'distinct'],
            'cooling_systems' => ['sometimes', 'nullable', 'array'], 'cooling_systems.*' => ['string', 'max:64', 'distinct'],
            'heating_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::HEATING_TYPES))],
            'cooling_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::COOLING_TYPES))],
            'flooring_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::FLOORING_TYPES))],
            'renovation_status' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::RENOVATION_STATUSES))],
            'building_orientation' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::ORIENTATIONS))],
            'deed_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::DEED_TYPES))],
            'has_loan' => ['sometimes', 'boolean'],
            'is_exchangeable' => ['sometimes', 'boolean'],
            'has_pool' => ['sometimes', 'boolean'],
            'has_jacuzzi' => ['sometimes', 'boolean'],
            'has_sauna' => ['sometimes', 'boolean'],
            'delivery_status' => ['sometimes', 'nullable', Rule::enum(DeliveryStatus::class)],
            'evacuation_date' => ['sometimes', 'nullable', 'date'],
            'owner' => ['sometimes', 'array:full_name,mobile,phone,notes'],
            'owner.full_name' => ['required_with:owner', 'string', 'max:200'],
            'owner.mobile' => ['required_with:owner', 'string', 'max:32'],
            'owner.phone' => ['nullable', 'string', 'max:32'],
            'owner.notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function toData(): UpdatePropertyData
    {
        $data = $this->validated();
        $expected = isset($data['expected_updated_at'])
            ? CarbonImmutable::parse($data['expected_updated_at'])
            : CarbonImmutable::createFromTimestampUTC(0);
        $expectedVersion = isset($data['expected_version']) ? (int) $data['expected_version'] : null;
        $owner = isset($data['owner']) ? new EmbeddedOwnerData(
            (string) $data['owner']['full_name'], (string) $data['owner']['mobile'],
            $data['owner']['phone'] ?? null, $data['owner']['notes'] ?? null,
        ) : null;
        $salePricePerSqm = isset($data['sale_price_per_sqm']) ? (string) $data['sale_price_per_sqm'] : null;
        $priceInputMode = (string) ($data['price_input_mode'] ?? 'total');
        unset($data['expected_updated_at'], $data['expected_version'], $data['owner'], $data['sale_price_per_sqm'], $data['price_input_mode']);
        foreach (['property_type' => PropertyType::class, 'transaction_type' => TransactionType::class] as $field => $enum) {
            if (isset($data[$field])) {
                $data[$field] = $enum::from($data[$field]);
            }
        }

        if (isset($data['delivery_status'])) {
            $data['delivery_status'] = DeliveryStatus::from($data['delivery_status']);
        }
        if (isset($data['evacuation_date'])) {
            $data['evacuation_date'] = CarbonImmutable::parse($data['evacuation_date']);
        }

        return new UpdatePropertyData($data, $expected, $expectedVersion, $owner, $salePricePerSqm, $priceInputMode);
    }
}
