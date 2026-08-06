<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Application\Customer\Data\CreateCustomerData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Property\Enums\PropertyType;
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
            'agency_id' => ['prohibited'], 'created_by_user_id' => ['prohibited'],
            'status' => ['prohibited'], 'converted_property_id' => ['prohibited'],
            'assigned_agent_id' => ['required', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'preferred_contact_method' => ['required', Rule::enum(PreferredContactMethod::class)],
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
        ];
    }

    public function toData(): CreateCustomerData
    {
        $data = $this->validated();

        return new CreateCustomerData(
            (int) $data['assigned_agent_id'], $data['first_name'], $data['last_name'],
            $data['mobile'], $data['phone'] ?? null, $data['email'] ?? null,
            PreferredContactMethod::from($data['preferred_contact_method']),
            CustomerIntent::from($data['intent']), $data['preferred_property_types'] ?? null,
            isset($data['budget_min']) ? (string) $data['budget_min'] : null,
            isset($data['budget_max']) ? (string) $data['budget_max'] : null,
            $data['desired_city'] ?? null, $data['desired_district'] ?? null,
            isset($data['min_area_sqm']) ? (string) $data['min_area_sqm'] : null,
            isset($data['max_area_sqm']) ? (string) $data['max_area_sqm'] : null,
            $data['min_bedrooms'] ?? null,
        );
    }
}
