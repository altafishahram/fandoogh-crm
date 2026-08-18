<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'agency' => $this->whenLoaded('agency', fn (): array => [
                'id' => $user->agency?->getKey(),
                'name' => $user->agency?->name,
                'timezone' => $user->agency?->timezone,
                'locale' => $user->agency?->locale,
                'currency_code' => $user->agency?->currency_code,
            ]),
        ];
    }
}
