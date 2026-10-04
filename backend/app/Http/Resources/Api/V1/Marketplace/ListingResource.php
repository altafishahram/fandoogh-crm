<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Marketplace;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PropertyPublication $listing */
        $listing = $this->resource;
        $property = $listing->property;
        abort_unless($property instanceof Property, 404);
        $images = $listing->images->map(fn (PropertyImage $image): array => ['id' => $image->getKey(), 'url' => route('api.v1.marketplace.images.content', ['listing' => $listing->getKey(), 'image' => $image->getKey(), 'audience' => $request->query('audience', 'public')]), 'sort_order' => $image->pivot->getAttribute('sort_order')])->values();
        $data = ['id' => $listing->getKey(), 'title' => $listing->public_title, 'property_type' => $property->property_type->value, 'transaction_type' => $property->transaction_type->value,
            'neighborhood' => $property->district, 'agency' => ['id' => $listing->agency->getKey(), 'name' => $listing->agency->name, 'phone' => $listing->agency->phone], 'published_at' => $listing->published_at?->toISOString(), 'cover_image_url' => $images->first()['url'] ?? null];
        foreach (['area_sqm', 'bedrooms', 'year_built', 'currency_code', 'currency_unit', 'sale_price', 'deposit_amount', 'monthly_rent'] as $field) {
            $data[$field] = $property->getAttribute($field);
        }
        if ($request->routeIs('api.v1.marketplace.listings.show')) {
            $data['description'] = $listing->public_description;
            $data['images'] = $images;
            $data['specifications'] = [];
            foreach (['bathrooms', 'floor_number', 'total_floors', 'parking_spaces', 'has_storage_room', 'has_elevator', 'has_balcony', 'units_per_floor', 'master_bedrooms', 'toilet_types', 'cabinet_type', 'heating_type', 'cooling_type', 'flooring_type', 'renovation_status', 'building_orientation', 'deed_type', 'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna', 'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas', 'land_area', 'building_area', 'can_aggregate', 'land_frontage', 'is_convertible', 'minimum_deposit'] as $field) {
                $data['specifications'][$field] = $property->getAttribute($field);
            }
        }

        return $data;
    }
}
