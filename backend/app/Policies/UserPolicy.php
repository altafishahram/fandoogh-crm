<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActive($user) && $user->can(PermissionName::UsersView->value);
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user) && $this->canManageTarget($user, $target);
    }

    public function create(User $user): bool
    {
        return $this->isActive($user) && $user->can(PermissionName::UsersCreate->value)
            && in_array($user->roleName(), [RoleName::SuperAdmin, RoleName::AgencyManager], true);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isActive($user)
            && $user->can(PermissionName::UsersUpdate->value)
            && $this->canManageTarget($user, $target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return ! $user->is($target)
            && $this->isActive($user)
            && $user->can(PermissionName::UsersDeactivate->value)
            && $this->canManageTarget($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->isActive($user)
            && $user->can(PermissionName::UsersResetPassword->value)
            && $this->canManageTarget($user, $target);
    }

    private function canManageTarget(User $user, User $target): bool
    {
        return match ($user->roleName()) {
            RoleName::SuperAdmin => $target->roleName() === RoleName::AgencyManager,
            RoleName::AgencyManager => $target->roleName() === RoleName::Agent
                && $user->agency_id === $target->agency_id,
            RoleName::Agent => false,
        };
    }

    private function isActive(User $user): bool
    {
        return $user->is_active && ! $user->trashed();
    }
}
