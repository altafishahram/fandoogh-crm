<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Property;

use App\Application\Property\Services\PropertyPricingCalculator;
use App\Http\Resources\Api\V1\Owner\OwnerSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PropertyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pricing = app(PropertyPricingCalculator::class);
        $perSquareMeter = $this->resource->sale_price !== null && $this->resource->area_sqm !== null
            ? $pricing->perSquareMeter((string) $this->resource->sale_price, (string) $this->resource->area_sqm)
            : null;
        $maximumRent = $this->resource->is_convertible && $this->resource->minimum_deposit !== null
            ? $pricing->maximumRent(
                (string) $this->resource->deposit_amount,
                (string) $this->resource->monthly_rent,
                (string) $this->resource->minimum_deposit,
            ) : null;

        return [
            'id' => $this->resource->getKey(),
            'code' => $this->resource->code,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'property_type' => $this->resource->property_type->value,
            'building_type' => $this->resource->building_type,
            'structure_type' => $this->resource->structure_type,
            'has_water' => $this->resource->has_water,
            'has_electricity' => $this->resource->has_electricity,
            'has_gas' => $this->resource->has_gas,
            'telephone_line_count' => $this->resource->telephone_line_count,
            'land_area' => $this->resource->land_area,
            'building_area' => $this->resource->building_area,
            'can_aggregate' => $this->resource->can_aggregate,
            'land_frontage' => $this->resource->land_frontage,
            'transaction_type' => $this->resource->transaction_type->value,
            'status' => $this->resource->status->value,
            'cover_image_url' => $this->coverImageUrl(),
            'assigned_agent_id' => $this->resource->assigned_agent_id,
            'currency_code' => $this->resource->currency_code,
            'currency_unit' => $this->resource->currency_unit,
            'sale_price' => $this->resource->sale_price,
            'deposit_amount' => $this->resource->deposit_amount,
            'monthly_rent' => $this->resource->monthly_rent,
            'area_sqm' => $this->resource->area_sqm,
            'sale_price_per_sqm' => $perSquareMeter,
            'bedrooms' => $this->resource->bedrooms,
            'bathrooms' => $this->resource->bathrooms,
            'floor_number' => $this->resource->floor_number,
            'total_floors' => $this->resource->total_floors,
            'year_built' => $this->resource->year_built,
            'parking_spaces' => $this->resource->parking_spaces,
            'has_storage_room' => $this->resource->has_storage_room,
            'has_elevator' => $this->resource->has_elevator,
            'has_balcony' => $this->resource->has_balcony,
            'master_bedrooms' => $this->resource->master_bedrooms,
            'has_master_bathroom' => $this->resource->has_master_bathroom,
            'toilet_types' => $this->resource->toilet_types,
            'units_per_floor' => $this->resource->units_per_floor,
            'cabinet_type' => $this->resource->cabinet_type,
            'heating_systems' => $this->resource->heating_systems,
            'heating_type' => $this->resource->heating_type,
            'cooling_systems' => $this->resource->cooling_systems,
            'cooling_type' => $this->resource->cooling_type,
            'flooring_type' => $this->resource->flooring_type,
            'renovation_status' => $this->resource->renovation_status,
            'building_orientation' => $this->resource->building_orientation,
            'deed_type' => $this->resource->deed_type,
            'has_loan' => $this->resource->has_loan,
            'is_exchangeable' => $this->resource->is_exchangeable,
            'has_pool' => $this->resource->has_pool,
            'has_jacuzzi' => $this->resource->has_jacuzzi,
            'has_sauna' => $this->resource->has_sauna,
            'city' => $this->resource->city,
            'district' => $this->resource->district,
            'street_address' => $this->resource->street_address,
            'plaque' => $this->resource->plaque,
            'postal_code' => $this->resource->postal_code,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'available_from' => $this->resource->available_from?->toDateString(),
            'delivery_status' => $this->resource->delivery_status?->value,
            'evacuation_date' => $this->resource->evacuation_date?->toDateString(),
            'is_convertible' => $this->resource->is_convertible,
            'minimum_deposit' => $this->resource->minimum_deposit,
            'maximum_rent' => $maximumRent,
            'created_by_user_id' => $this->resource->created_by_user_id,
            'created_by_role' => $this->resource->created_by_role,
            'lock_version' => $this->resource->lock_version,
            'closed_at' => $this->resource->closed_at?->toISOString(),
            'archived_at' => $this->resource->archived_at?->toISOString(),
            'owners' => OwnerSummaryResource::collection($this->whenLoaded('owners')),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
            'deleted_at' => $this->resource->deleted_at?->toISOString(),
        ];
    }

    private function coverImageUrl(): ?string
    {
        if (! $this->resource->relationLoaded('images')) {
            return null;
        }

        $image = $this->resource->images->firstWhere('is_cover', true)
            ?? $this->resource->images->sortBy('sort_order')->first();
        if ($image === null) {
            return null;
        }

        return route('api.v1.properties.images.content', [
            'property' => $this->resource->getKey(),
            'image' => $image->getKey(),
        ]);
    }
}
