<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Agency\Services\UpdateAgencyService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Http\Request;

final class AgencyLocationController extends Controller
{
    public function update(Request $request, UpdateAgencyService $service): AuthenticatedUserResource
    {
        $data = $request->validate(['province_id' => ['required', 'integer'], 'county_id' => ['required', 'integer'], 'city_id' => ['required', 'integer']]);
        $user = $request->user();
        abort_unless($user instanceof User && $user->agency !== null, 403);
        $service->execute($user, $user->agency, $data);

        return new AuthenticatedUserResource($user->load(['agency', 'roles.permissions']));
    }
}
