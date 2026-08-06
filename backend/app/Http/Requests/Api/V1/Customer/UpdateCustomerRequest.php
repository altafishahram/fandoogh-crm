<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Application\Customer\Data\UpdateCustomerData;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Property\Enums\PropertyType;
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
            'agency_id' => ['prohibited'], 'created_by_user_id' => ['prohibited'],
            'expected_updated_at' => ['required', 'date'],
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
        ];
    }

    public function toData(): UpdateCustomerData
    {
        $data = $this->validated();
        $expected = CarbonImmutable::parse($data['expected_updated_at']);
        unset($data['expected_updated_at']);
        foreach ([
            'preferred_contact_method' => PreferredContactMethod::class,
            'intent' => CustomerIntent::class,
            'status' => CustomerStatus::class,
        ] as $field => $enum) {
            if (isset($data[$field])) {
                $data[$field] = $enum::from($data[$field]);
            }
        }

        return new UpdateCustomerData($data, $expected);
    }
}
