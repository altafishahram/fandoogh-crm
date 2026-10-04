<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OwnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'full_name' => $this->resource->full_name,
            'owner_type' => $this->resource->owner_type->value,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'company_name' => $this->resource->company_name,
            'mobile' => $this->resource->mobile,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'identity_number' => $this->resource->identity_number_encrypted,
            'address_line_1' => $this->resource->address_line_1,
            'address_line_2' => $this->resource->address_line_2,
            'city' => $this->resource->city,
            'province_id' => $this->resource->province_id, 'county_id' => $this->resource->county_id, 'city_id' => $this->resource->city_id,
            'province' => $this->resource->province,
            'postal_code' => $this->resource->postal_code,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
