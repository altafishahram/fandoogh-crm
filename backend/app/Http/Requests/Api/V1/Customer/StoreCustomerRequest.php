<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Application\Customer\Data\CreateCustomerData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\PropertyFeatureOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
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
            'status' => ['prohibited'], 'converted_property_id' => ['prohibited'],
            'assigned_agent_id' => ['nullable', 'integer', 'min:1'],
            'full_name' => ['nullable', 'required_without_all:first_name,last_name', 'string', 'max:200'],
            'first_name' => ['nullable', 'required_without:full_name', 'string', 'max:100'],
            'last_name' => ['nullable', 'required_without:full_name', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'preferred_contact_method' => ['nullable', Rule::enum(PreferredContactMethod::class)],
            'intent' => ['required', Rule::enum(CustomerIntent::class)],
            'preferred_property_types' => ['nullable', 'array'],
            'preferred_property_types.*' => ['required', Rule::enum(PropertyType::class), 'distinct'],
            'budget_min' => ['nullable', 'decimal:0,2', 'gte:0'],
            'budget_max' => ['nullable', 'decimal:0,2', 'gte:budget_min'],
            'desired_city' => ['nullable', 'string', 'max:100'],
            'desired_district' => ['nullable', 'string', 'max:100'],
            'min_area_sqm' => ['nullable', 'decimal:0,2', 'gt:0'],
            'max_area_sqm' => ['nullable', 'decimal:0,2', 'gte:min_area_sqm'],
            'min_bedrooms' => ['nullable', 'integer', 'between:0,255'],
            'toilet_types' => ['nullable', 'array', 'max:2'],
            'toilet_types.*' => ['required', Rule::in(array_keys(PropertyFeatureOptions::TOILET_TYPES)), 'distinct'],
            'has_master_bathroom' => ['sometimes', 'boolean'],
            'cabinet_type' => ['nullable', Rule::in(array_keys(PropertyFeatureOptions::CABINET_TYPES))],
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
            'desired_property_type' => ['nullable', 'required_with:full_name', Rule::enum(PropertyType::class)],
            'building_type' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($this->input('desired_property_type'), ['house', 'villa', 'house_villa'], true)),
                Rule::in(array_keys(PropertyFeatureOptions::BUILDING_TYPES)),
            ],
            'structure_type' => ['nullable', 'string', 'max:160'],
            'has_water' => ['sometimes', 'boolean'],
            'has_electricity' => ['sometimes', 'boolean'],
            'has_gas' => ['sometimes', 'boolean'],
            'telephone_line_count' => ['nullable', 'string', 'max:32'],
            'land_area' => ['nullable', 'string', 'max:64'],
            'building_area' => ['nullable', 'string', 'max:64'],
            // Legacy industrial customers may still send the original single
            // area notes. New records use the four numeric range fields.
            'land_area_min' => [Rule::requiredIf(fn (): bool => $this->isIndustrial() && ! $this->hasLegacyIndustrialAreas()), 'nullable', 'numeric', 'gt:0'],
            'land_area_max' => [Rule::requiredIf(fn (): bool => $this->isIndustrial() && ! $this->hasLegacyIndustrialAreas()), 'nullable', 'numeric', 'gt:0', 'gte:land_area_min'],
            'building_area_min' => [Rule::requiredIf(fn (): bool => $this->isIndustrial() && ! $this->hasLegacyIndustrialAreas()), 'nullable', 'numeric', 'gt:0'],
            'building_area_max' => [Rule::requiredIf(fn (): bool => $this->isIndustrial() && ! $this->hasLegacyIndustrialAreas()), 'nullable', 'numeric', 'gt:0', 'gte:building_area_min'],
            'min_parking_spaces' => ['nullable', 'integer', 'between:0,255'],
            'has_parking' => ['sometimes', 'boolean'],
            'has_storage_room' => ['sometimes', 'boolean'],
            'owner_resides' => ['sometimes', 'boolean'],
            'has_elevator' => ['sometimes', 'boolean'],
            'has_balcony' => ['sometimes', 'boolean'],
            'rental_deposit_min' => ['nullable', 'required_if:intent,rent', 'decimal:0,2', 'gte:0'],
            'rental_deposit_max' => ['nullable', 'required_if:intent,rent', 'decimal:0,2', 'gte:rental_deposit_min'],
            'rental_rent_min' => ['nullable', 'required_if:intent,rent', 'decimal:0,2', 'gte:0'],
            'rental_rent_max' => ['nullable', 'required_if:intent,rent', 'decimal:0,2', 'gte:rental_rent_min'],
            'accepts_rent_conversion' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function toData(): CreateCustomerData
    {
        $data = $this->validated();

        return new CreateCustomerData(
            isset($data['assigned_agent_id']) ? (int) $data['assigned_agent_id'] : null,
            (string) ($data['first_name'] ?? $data['full_name']), (string) ($data['last_name'] ?? ''),
            $data['mobile'], $data['phone'] ?? null, $data['email'] ?? null,
            PreferredContactMethod::from($data['preferred_contact_method'] ?? 'phone'),
            CustomerIntent::from($data['intent']), $data['preferred_property_types'] ?? null,
            isset($data['budget_min']) ? (string) $data['budget_min'] : null,
            isset($data['budget_max']) ? (string) $data['budget_max'] : null,
            $data['desired_city'] ?? null, $data['desired_district'] ?? null,
            isset($data['min_area_sqm']) ? (string) $data['min_area_sqm'] : null,
            isset($data['max_area_sqm']) ? (string) $data['max_area_sqm'] : null,
            $data['min_bedrooms'] ?? null,
            $data['full_name'] ?? null, $data['desired_property_type'] ?? null,
            isset($data['rental_deposit_min']) ? (string) $data['rental_deposit_min'] : null,
            isset($data['rental_deposit_max']) ? (string) $data['rental_deposit_max'] : null,
            isset($data['rental_rent_min']) ? (string) $data['rental_rent_min'] : null,
            isset($data['rental_rent_max']) ? (string) $data['rental_rent_max'] : null,
            (bool) ($data['accepts_rent_conversion'] ?? true), $data['description'] ?? null,
            $data['toilet_types'] ?? null,
            (bool) ($data['has_master_bathroom'] ?? false),
            $data['cabinet_type'] ?? null,
            $data['heating_type'] ?? null,
            $data['cooling_type'] ?? null,
            $data['flooring_type'] ?? null,
            $data['renovation_status'] ?? null,
            $data['building_orientation'] ?? null,
            $data['deed_type'] ?? null,
            (bool) ($data['has_loan'] ?? false),
            (bool) ($data['is_exchangeable'] ?? false),
            (bool) ($data['has_pool'] ?? false),
            (bool) ($data['has_jacuzzi'] ?? false),
            (bool) ($data['has_sauna'] ?? false),
            $data['building_type'] ?? null, $data['structure_type'] ?? null,
            (bool) ($data['has_water'] ?? false), (bool) ($data['has_electricity'] ?? false),
            (bool) ($data['has_gas'] ?? false), $data['telephone_line_count'] ?? null,
            $data['land_area'] ?? null, $data['building_area'] ?? null,
            $data['intent'] === CustomerIntent::Rent->value ? ($data['min_parking_spaces'] ?? null) : null,
            $data['intent'] === CustomerIntent::Rent->value && (bool) ($data['has_elevator'] ?? false),
            $data['intent'] === CustomerIntent::Rent->value && (bool) ($data['has_balcony'] ?? false),
            (bool) ($data['has_parking'] ?? (($data['min_parking_spaces'] ?? 0) > 0)),
            (bool) ($data['has_storage_room'] ?? false),
            $data['intent'] === CustomerIntent::Rent->value && (bool) ($data['owner_resides'] ?? false),
            isset($data['land_area_min']) ? (string) $data['land_area_min'] : null,
            isset($data['land_area_max']) ? (string) $data['land_area_max'] : null,
            isset($data['building_area_min']) ? (string) $data['building_area_min'] : null,
            isset($data['building_area_max']) ? (string) $data['building_area_max'] : null,
            isset($data['desired_province_id']) ? (int) $data['desired_province_id'] : null,
            isset($data['desired_county_id']) ? (int) $data['desired_county_id'] : null,
            isset($data['desired_city_id']) ? (int) $data['desired_city_id'] : null,
        );
    }

    private function isIndustrial(): bool
    {
        return $this->input('desired_property_type') === PropertyType::Industrial->value;
    }

    private function hasLegacyIndustrialAreas(): bool
    {
        return filled($this->input('land_area')) && filled($this->input('building_area'));
    }
}
