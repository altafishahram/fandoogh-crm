<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Application\Customer\Data\UpdateCustomerData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\PropertyFeatureOptions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'desired_province_id' => ['sometimes', 'nullable', 'integer'],
            'desired_county_id' => ['sometimes', 'nullable', 'integer'],
            'desired_city_id' => ['sometimes', 'nullable', 'integer'],

            'agency_id' => ['prohibited'], 'created_by_user_id' => ['prohibited'],
            'expected_updated_at' => ['required_without:expected_version', 'date'],
            'expected_version' => ['required_without:expected_updated_at', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'assigned_agent_id' => ['sometimes', 'integer', 'min:1'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'mobile' => ['sometimes', 'string', 'max:32'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'preferred_contact_method' => ['sometimes', Rule::enum(PreferredContactMethod::class)],
            'intent' => ['sometimes', Rule::enum(CustomerIntent::class)],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'converted_property_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'preferred_property_types' => ['sometimes', 'nullable', 'array'],
            'preferred_property_types.*' => ['required', Rule::enum(PropertyType::class), 'distinct'],
            'budget_min' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'budget_max' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'desired_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'desired_district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'min_area_sqm' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'max_area_sqm' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'min_bedrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'toilet_types' => ['sometimes', 'nullable', 'array', 'max:2'],
            'toilet_types.*' => ['required', Rule::in(array_keys(PropertyFeatureOptions::TOILET_TYPES)), 'distinct'],
            'has_master_bathroom' => ['sometimes', 'boolean'],
            'cabinet_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::CABINET_TYPES))],
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
            'full_name' => ['sometimes', 'string', 'max:200'],
            'desired_property_type' => ['sometimes', Rule::enum(PropertyType::class)],
            'building_type' => ['sometimes', 'nullable', Rule::in(array_keys(PropertyFeatureOptions::BUILDING_TYPES))],
            'structure_type' => ['sometimes', 'nullable', 'string', 'max:160'],
            'has_water' => ['sometimes', 'boolean'],
            'has_electricity' => ['sometimes', 'boolean'],
            'has_gas' => ['sometimes', 'boolean'],
            'telephone_line_count' => ['sometimes', 'nullable', 'string', 'max:32'],
            'land_area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'building_area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'land_area_min' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'land_area_max' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'gte:land_area_min'],
            'building_area_min' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'building_area_max' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'gte:building_area_min'],
            'min_parking_spaces' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'has_parking' => ['sometimes', 'boolean'],
            'has_storage_room' => ['sometimes', 'boolean'],
            'owner_resides' => ['sometimes', 'boolean'],
            'has_elevator' => ['sometimes', 'boolean'],
            'has_balcony' => ['sometimes', 'boolean'],
            'rental_deposit_min' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'rental_deposit_max' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'rental_rent_min' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'rental_rent_max' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'accepts_rent_conversion' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function toData(): UpdateCustomerData
    {
        $data = $this->validated();
        $expected = isset($data['expected_updated_at'])
            ? CarbonImmutable::parse($data['expected_updated_at'])
            : CarbonImmutable::createFromTimestampUTC(0);
        $expectedVersion = isset($data['expected_version']) ? (int) $data['expected_version'] : null;
        $reason = $data['reason'] ?? null;
        unset($data['expected_updated_at'], $data['expected_version'], $data['reason']);
        foreach ([
            'preferred_contact_method' => PreferredContactMethod::class,
            'intent' => CustomerIntent::class,
            'status' => CustomerStatus::class,
        ] as $field => $enum) {
            if (isset($data[$field])) {
                $data[$field] = $enum::from($data[$field]);
            }
        }

        return new UpdateCustomerData($data, $expected, $expectedVersion, $reason);
    }
}
