<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class UpdateAgentPermissionsService
{
    /** @param list<string> $permissions */
    public function execute(User $actor, User $agent, array $permissions): User
    {
        if (! $actor->is_active
            || $actor->roleName() !== RoleName::AgencyManager
            || $agent->roleName() !== RoleName::Agent
            || $actor->agency_id !== $agent->agency_id) {
            throw new AuthorizationException;
        }

        $allowed = array_map(
            static fn (PermissionName $permission): string => $permission->value,
            RoleName::agentConfigurablePermissions(),
        );
        if (array_diff($permissions, $allowed) !== []) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($agent, $permissions): User {
            $agent->syncPermissions(array_values(array_unique($permissions)));
            $agent->tokens()->delete();

            return $agent->load('permissions');
        });
    }
}
