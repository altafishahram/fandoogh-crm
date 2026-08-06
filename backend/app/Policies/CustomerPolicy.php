<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::CustomersView->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user)
            && $user->agency_id === $customer->agency_id
            && ($user->roleName() === RoleName::AgencyManager
                || $customer->assigned_agent_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::CustomersCreate->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomersUpdate->value);
    }

    public function assign(User $user, Customer $customer): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $customer->agency_id
            && $user->can(PermissionName::CustomersAssign->value);
    }

    public function manageNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomersManageNotes->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $customer->agency_id
            && $user->can(PermissionName::CustomersDelete->value);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $customer->agency_id
            && $user->can(PermissionName::CustomersRestore->value);
    }
}
