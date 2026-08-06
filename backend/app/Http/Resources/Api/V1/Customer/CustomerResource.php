<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'assigned_agent_id' => $this->resource->assigned_agent_id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'mobile' => $this->resource->mobile,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'preferred_contact_method' => $this->resource->preferred_contact_method->value,
            'intent' => $this->resource->intent->value,
            'status' => $this->resource->status->value,
            'preferred_property_types' => $this->resource->preferred_property_types,
            'budget_min' => $this->resource->budget_min,
            'budget_max' => $this->resource->budget_max,
            'desired_city' => $this->resource->desired_city,
            'desired_district' => $this->resource->desired_district,
            'min_area_sqm' => $this->resource->min_area_sqm,
            'max_area_sqm' => $this->resource->max_area_sqm,
            'min_bedrooms' => $this->resource->min_bedrooms,
            'converted_property_id' => $this->resource->converted_property_id,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
