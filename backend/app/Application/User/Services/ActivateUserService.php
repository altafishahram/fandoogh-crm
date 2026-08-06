<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class ActivateUserService
{
    public function execute(User $actor, User $target): User
    {
        $canManage = match ($actor->roleName()) {
            RoleName::SuperAdmin => $target->roleName() === RoleName::AgencyManager,
            RoleName::AgencyManager => $target->roleName() === RoleName::Agent
                && $actor->agency_id === $target->agency_id,
            RoleName::Agent => false,
        };
        if (! $actor->is_active || ! $actor->can(PermissionName::UsersDeactivate->value) || ! $canManage) {
            throw new AuthorizationException;
        }

        $target->is_active = true;
        $target->save();

        return $target->refresh();
    }
}
