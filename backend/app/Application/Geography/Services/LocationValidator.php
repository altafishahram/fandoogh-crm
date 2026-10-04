<?php

declare(strict_types=1);

namespace App\Application\Geography\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LocationValidator
{
    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function normalize(array $attributes, string $prefix = ''): array
    {
        $fields = [$prefix.'province_id', $prefix.'county_id', $prefix.'city_id'];
        if (array_intersect($fields, array_keys($attributes)) === []) {
            return $attributes;
        }
        $province = $attributes[$fields[0]] ?? null;
        $county = $attributes[$fields[1]] ?? null;
        $city = $attributes[$fields[2]] ?? null;
        if ($province === null && $county === null && $city === null) {
            return $attributes;
        }
        $cityRow = DB::table('location_cities')->where('id', $city)->first();
        $countyRow = DB::table('location_counties')->where('id', $county)->first();
        $provinceRow = DB::table('location_provinces')->where('id', $province)->first();
        if ($provinceRow === null || $countyRow === null || $cityRow === null
            || (int) $countyRow->province_id !== (int) $province || (int) $cityRow->county_id !== (int) $county) {
            throw ValidationException::withMessages([$fields[2] => ['استان، شهرستان و شهر باید یک مسیر معتبر باشند.']]);
        }
        $attributes[$prefix.'city'] = $cityRow->name;
        if ($prefix === '') {
            $attributes['province'] = $provinceRow->name;
        }

        return $attributes;
    }
}
