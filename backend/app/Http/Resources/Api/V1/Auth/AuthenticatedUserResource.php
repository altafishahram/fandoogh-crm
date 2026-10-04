<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Application\Marketplace\Services\PublicationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/** @mixin User */
final class AuthenticatedUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'must_change_password' => $user->must_change_password,
            'role' => $user->roleName()->value,
            'permissions' => $permissions,
            'capabilities' => [
                'can_publish' => app(PublicationService::class)->canPublish($user),
                'can_browse_agency_marketplace' => $user->agency !== null && $user->agency->is_active && $user->agency->is_verified,
            ],
            'agency' => $this->whenLoaded('agency', fn (): array => [
                'id' => $user->agency?->getKey(),
                'name' => $user->agency?->name,
                'province_id' => $user->agency?->province_id,
                'county_id' => $user->agency?->county_id,
                'city_id' => $user->agency?->city_id,
                'province' => $user->agency?->province,
                'county' => DB::table('location_counties')->where('id', $user->agency?->county_id)->value('name'),
                'city' => $user->agency?->city,
                'is_verified' => (bool) $user->agency?->is_verified,
                'timezone' => $user->agency?->timezone,
                'locale' => $user->agency?->locale,
                'currency_code' => $user->agency?->currency_code,
            ]),
        ];
    }
}
