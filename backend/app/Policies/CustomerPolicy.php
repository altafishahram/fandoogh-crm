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
            && $user->agency_id === $customer->agency_id;
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

    public function changeStatus(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomersChangeStatus->value);
    }

    public function manageNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomersManageNotes->value);
    }

    public function viewNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomerNotesView->value);
    }

    public function createNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomerNotesCreate->value);
    }

    public function updateNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomerNotesUpdate->value);
    }

    public function deleteNotes(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer)
            && $user->can(PermissionName::CustomerNotesDelete->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_active
            && $user->agency_id === $customer->agency_id
            && $user->can(PermissionName::CustomersDelete->value);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->is_active
            && $user->agency_id === $customer->agency_id
            && $user->can(PermissionName::CustomersRestore->value);
    }
}
