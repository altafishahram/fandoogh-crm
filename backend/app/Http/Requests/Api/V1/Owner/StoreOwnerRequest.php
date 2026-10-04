<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Owner;

use App\Application\Owner\Data\OwnerData;
use App\Domain\Owner\Enums\OwnerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'province_id' => ['nullable', 'integer'], 'county_id' => ['nullable', 'integer'], 'city_id' => ['nullable', 'integer'],
            'agency_id' => ['prohibited'], 'created_by_user_id' => ['prohibited'],
            'owner_type' => ['required', Rule::enum(OwnerType::class)],
            'first_name' => ['nullable', 'required_if:owner_type,person', 'string', 'max:100'],
            'last_name' => ['nullable', 'required_if:owner_type,person', 'string', 'max:100'],
            'company_name' => ['nullable', 'required_if:owner_type,company', 'string', 'max:180'],
            'mobile' => ['nullable', 'required_without_all:phone,email', 'string', 'max:32'],
            'phone' => ['nullable', 'required_without_all:mobile,email', 'string', 'max:32'],
            'email' => ['nullable', 'required_without_all:mobile,phone', 'email:rfc', 'max:254'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): OwnerData
    {
        $data = $this->validated();

        return new OwnerData(
            OwnerType::from($data['owner_type']),
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['company_name'] ?? null,
            $data['mobile'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['identity_number'] ?? null,
            $data['address_line_1'] ?? null,
            $data['address_line_2'] ?? null,
            $data['city'] ?? null,
            $data['province'] ?? null,
            $data['postal_code'] ?? null,
            $data['notes'] ?? null,
            isset($data['province_id']) ? (int) $data['province_id'] : null,
            isset($data['county_id']) ? (int) $data['county_id'] : null,
            isset($data['city_id']) ? (int) $data['city_id'] : null,
        );
    }
}
