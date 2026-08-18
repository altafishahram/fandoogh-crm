<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Owner;
use App\Models\User;

final class OwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::OwnersView->value);
    }

    public function view(User $user, Owner $owner): bool
    {
        if (! $this->viewAny($user) || $user->agency_id !== $owner->agency_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::OwnersCreate->value);
    }

    public function update(User $user, Owner $owner): bool
    {
        if (! $user->is_active || ! $user->can(PermissionName::OwnersUpdate->value)
            || $user->agency_id !== $owner->agency_id) {
            return false;
        }

        return $owner->properties()
            ->whereIn('status', [PropertyStatus::Available->value, PropertyStatus::Reserved->value])
            ->exists();
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $owner->agency_id
            && $user->can(PermissionName::OwnersDelete->value);
    }

    public function restore(User $user, Owner $owner): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $owner->agency_id
            && $user->can(PermissionName::OwnersRestore->value);
    }
}
