<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use App\Application\Property\Data\UpdatePropertyData;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
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
            'agency_id' => ['prohibited'], 'code' => ['prohibited'], 'currency_code' => ['prohibited'],
            'status' => ['prohibited'], 'created_by_user_id' => ['prohibited'], 'owners' => ['prohibited'],
            'expected_updated_at' => ['required', 'date'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'property_type' => ['sometimes', Rule::enum(PropertyType::class)],
            'transaction_type' => ['sometimes', Rule::enum(TransactionType::class)],
            'assigned_agent_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sale_price' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'deposit_amount' => ['sometimes', 'nullable', 'decimal:0,2', 'gte:0'],
            'monthly_rent' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'area_sqm' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
            'floor_number' => ['sometimes', 'nullable', 'integer', 'between:-32768,32767'],
            'total_floors' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
            'year_built' => ['sometimes', 'nullable', 'integer', 'min:1800'],
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
        ];
    }

    public function toData(): UpdatePropertyData
    {
        $data = $this->validated();
        $expected = CarbonImmutable::parse($data['expected_updated_at']);
        unset($data['expected_updated_at']);
        foreach (['property_type' => PropertyType::class, 'transaction_type' => TransactionType::class] as $field => $enum) {
            if (isset($data[$field])) {
                $data[$field] = $enum::from($data[$field]);
            }
        }

        return new UpdatePropertyData($data, $expected);
    }
}
