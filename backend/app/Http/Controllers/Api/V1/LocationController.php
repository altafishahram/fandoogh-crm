<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LocationController extends Controller
{
    public function provinces(): mixed
    {
        return response()->json(['data' => DB::table('location_provinces')->orderBy('name')->get(['id', 'name']), 'meta' => ['version' => '1404']]);
    }

    public function counties(Request $request): mixed
    {
        $data = $request->validate(['province_id' => ['required', 'integer', 'exists:location_provinces,id']]);

        return response()->json(['data' => DB::table('location_counties')->where('province_id', $data['province_id'])->orderBy('name')->get(['id', 'name', 'province_id']), 'meta' => ['version' => '1404']]);
    }

    public function cities(Request $request): mixed
    {
        $data = $request->validate(['county_id' => ['required', 'integer', 'exists:location_counties,id']]);

        return response()->json(['data' => DB::table('location_cities')->where('county_id', $data['county_id'])->orderBy('name')->get(['id', 'name', 'county_id']), 'meta' => ['version' => '1404']]);
    }
}
