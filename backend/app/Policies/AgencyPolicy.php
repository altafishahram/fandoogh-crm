<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;

final class AgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveSuperAdmin($user)
            && $user->can(PermissionName::AgenciesView->value);
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveSuperAdmin($user)
            && $user->can(PermissionName::AgenciesCreate->value);
    }

    public function update(User $user, Agency $agency): bool
    {
        return $this->isActiveSuperAdmin($user)
            && $user->can(PermissionName::AgenciesUpdate->value);
    }

    public function activate(User $user, Agency $agency): bool
    {
        return $this->isActiveSuperAdmin($user)
            && $user->can(PermissionName::AgenciesActivate->value);
    }

    private function isActiveSuperAdmin(User $user): bool
    {
        return $user->is_active
            && ! $user->trashed()
            && $user->agency_id === null
            && $user->roleName() === RoleName::SuperAdmin;
    }
}
