<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Property;

use App\Http\Resources\Api\V1\Owner\OwnerSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PropertyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'code' => $this->resource->code,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'property_type' => $this->resource->property_type->value,
            'transaction_type' => $this->resource->transaction_type->value,
            'status' => $this->resource->status->value,
            'assigned_agent_id' => $this->resource->assigned_agent_id,
            'currency_code' => $this->resource->currency_code,
            'sale_price' => $this->resource->sale_price,
            'deposit_amount' => $this->resource->deposit_amount,
            'monthly_rent' => $this->resource->monthly_rent,
            'area_sqm' => $this->resource->area_sqm,
            'bedrooms' => $this->resource->bedrooms,
            'bathrooms' => $this->resource->bathrooms,
            'floor_number' => $this->resource->floor_number,
            'total_floors' => $this->resource->total_floors,
            'year_built' => $this->resource->year_built,
            'parking_spaces' => $this->resource->parking_spaces,
            'has_storage_room' => $this->resource->has_storage_room,
            'has_elevator' => $this->resource->has_elevator,
            'has_balcony' => $this->resource->has_balcony,
            'city' => $this->resource->city,
            'district' => $this->resource->district,
            'street_address' => $this->resource->street_address,
            'postal_code' => $this->resource->postal_code,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'available_from' => $this->resource->available_from?->toDateString(),
            'closed_at' => $this->resource->closed_at?->toISOString(),
            'archived_at' => $this->resource->archived_at?->toISOString(),
            'owners' => OwnerSummaryResource::collection($this->whenLoaded('owners')),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
