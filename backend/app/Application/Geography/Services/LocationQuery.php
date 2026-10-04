<?php

declare(strict_types=1);

namespace App\Application\Geography\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class LocationQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public function apply(Builder $query, array $filters, string $prefix = ''): Builder
    {
        $filters = array_filter(array_intersect_key($filters, array_flip(['province_id', 'county_id', 'city_id'])), fn ($value) => $value !== null);
        if ($filters === []) {
            return $query;
        }
        // Compatibility is read-only and limited to globally unambiguous city names.
        // Never infer a record's location from its agency or silently persist IDs.
        $names = DB::table('location_cities as city')->join('location_counties as county', 'county.id', '=', 'city.county_id')
            ->select('city.name')->whereNotIn('city.name', DB::table('location_cities')->select('name')->groupBy('name')->havingRaw('COUNT(*) > 1'));
        foreach ($filters as $field => $value) {
            $names->where(match ($field) {
                'province_id' => 'county.province_id', 'county_id' => 'county.id', 'city_id' => 'city.id',
                default => throw new \LogicException('Unsupported location filter.'),
            }, $value);
        }

        return $query->where(function (Builder $scope) use ($filters, $prefix, $names): void {
            $scope->where(function (Builder $located) use ($filters, $prefix): void {
                foreach ($filters as $field => $value) {
                    $located->where($prefix.$field, $value);
                }
            })->orWhere(function (Builder $legacy) use ($prefix, $names): void {
                $legacy->whereNull($prefix.'province_id')->whereNull($prefix.'county_id')->whereNull($prefix.'city_id')
                    ->whereIn($prefix.'city', $names);
            });
        });
    }
}
