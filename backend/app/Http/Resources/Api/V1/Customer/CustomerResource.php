<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Application\Matching\Services\RelatedMatchQuery;
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
            'full_name' => $this->resource->full_name ?: trim($this->resource->first_name.' '.$this->resource->last_name),
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'mobile' => $this->resource->mobile,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'preferred_contact_method' => $this->resource->preferred_contact_method->value,
            'intent' => $this->resource->intent->value,
            'status' => $this->resource->status->value,
            'match_summary' => RelatedMatchQuery::summaryOf($this->resource),
            'preferred_property_types' => $this->resource->preferred_property_types,
            'desired_property_type' => $this->resource->desired_property_type,
            'building_type' => $this->resource->building_type,
            'structure_type' => $this->resource->structure_type,
            'has_water' => $this->resource->has_water,
            'has_electricity' => $this->resource->has_electricity,
            'has_gas' => $this->resource->has_gas,
            'telephone_line_count' => $this->resource->telephone_line_count,
            'land_area' => $this->resource->land_area,
            'building_area' => $this->resource->building_area,
            'land_area_min' => $this->resource->land_area_min,
            'land_area_max' => $this->resource->land_area_max,
            'building_area_min' => $this->resource->building_area_min,
            'building_area_max' => $this->resource->building_area_max,
            'min_parking_spaces' => $this->resource->min_parking_spaces,
            'has_parking' => $this->resource->has_parking,
            'has_storage_room' => $this->resource->has_storage_room,
            'owner_resides' => $this->resource->owner_resides,
            'has_elevator' => $this->resource->has_elevator,
            'has_balcony' => $this->resource->has_balcony,
            'budget_min' => $this->resource->budget_min,
            'budget_max' => $this->resource->budget_max,
            'desired_city' => $this->resource->desired_city,
            'desired_province_id' => $this->resource->desired_province_id,
            'desired_county_id' => $this->resource->desired_county_id,
            'desired_city_id' => $this->resource->desired_city_id,
            'desired_district' => $this->resource->desired_district,
            'min_area_sqm' => $this->resource->min_area_sqm,
            'max_area_sqm' => $this->resource->max_area_sqm,
            'min_bedrooms' => $this->resource->min_bedrooms,
            'toilet_types' => $this->resource->toilet_types,
            'has_master_bathroom' => $this->resource->has_master_bathroom,
            'cabinet_type' => $this->resource->cabinet_type,
            'heating_type' => $this->resource->heating_type,
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
            'rental_deposit_min' => $this->resource->rental_deposit_min,
            'rental_deposit_max' => $this->resource->rental_deposit_max,
            'rental_rent_min' => $this->resource->rental_rent_min,
            'rental_rent_max' => $this->resource->rental_rent_max,
            'accepts_rent_conversion' => $this->resource->accepts_rent_conversion,
            'description' => $this->resource->description,
            'created_by_user_id' => $this->resource->created_by_user_id,
            'created_by_role' => $this->resource->created_by_role,
            'lock_version' => $this->resource->lock_version,
            'converted_property_id' => $this->resource->converted_property_id,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
            'deleted_at' => $this->resource->deleted_at?->toISOString(),
        ];
    }
}
