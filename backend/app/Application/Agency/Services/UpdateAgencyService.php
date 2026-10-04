<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Application\Geography\Services\LocationValidator;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class UpdateAgencyService
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Agency $agency, array $attributes): Agency
    {
        $platformUpdate = $actor->roleName() === RoleName::SuperAdmin
            && $actor->can(PermissionName::AgenciesUpdate->value);
        $tenantUpdate = $actor->roleName() === RoleName::AgencyManager
            && $actor->agency_id === $agency->getKey()
            && $actor->can(PermissionName::AgencySettingsUpdate->value);

        if (! $actor->is_active || (! $platformUpdate && ! $tenantUpdate)) {
            throw new AuthorizationException;
        }

        $allowed = [
            'province_id', 'county_id', 'city_id',
            'name', 'slug', 'email', 'phone', 'address_line_1', 'address_line_2', 'city',
            'province', 'postal_code', 'country_code', 'timezone', 'locale', 'currency_code',
        ];
        if ($tenantUpdate) {
            $allowed = [
                'province_id', 'county_id', 'city_id',
                'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'province',
                'postal_code', 'timezone', 'locale',
            ];
        }

        $attributes = app(LocationValidator::class)->normalize($attributes);
        $agency->fill(array_intersect_key($attributes, array_flip($allowed)))->save();

        return $agency->refresh();
    }
}
