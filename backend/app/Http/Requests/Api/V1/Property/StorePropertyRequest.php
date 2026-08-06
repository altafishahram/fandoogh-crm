<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Property;

use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
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
        $nextYear = ((int) date('Y')) + 1;

        return [
            'agency_id' => ['prohibited'], 'code' => ['prohibited'], 'currency_code' => ['prohibited'],
            'status' => ['prohibited'], 'created_by_user_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'transaction_type' => ['required', Rule::enum(TransactionType::class)],
            'assigned_agent_id' => ['nullable', 'integer', 'min:1'],
            'sale_price' => ['nullable', 'required_if:transaction_type,sale', 'decimal:0,2', 'gt:0'],
            'deposit_amount' => ['nullable', 'required_if:transaction_type,rent', 'decimal:0,2', 'gte:0'],
            'monthly_rent' => ['nullable', 'required_if:transaction_type,rent', 'decimal:0,2', 'gt:0'],
            'area_sqm' => ['nullable', 'decimal:0,2', 'gt:0'],
            'bedrooms' => ['nullable', 'integer', 'between:0,255'],
            'bathrooms' => ['nullable', 'integer', 'between:0,255'],
            'floor_number' => ['nullable', 'integer', 'between:-32768,32767', 'lte:total_floors'],
            'total_floors' => ['nullable', 'integer', 'between:0,65535'],
            'year_built' => ['nullable', 'integer', 'between:1800,'.$nextYear],
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
            'owners' => ['required', 'array', 'min:1'],
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
            $data['owners'],
        ));

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
        );
    }
}
