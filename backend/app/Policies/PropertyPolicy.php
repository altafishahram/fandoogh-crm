<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;

final class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::PropertiesView->value);
    }

    public function view(User $user, Property $property): bool
    {
        return $this->viewAny($user)
            && $user->agency_id === $property->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::PropertiesCreate->value);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertiesUpdate->value);
    }

    public function changeStatus(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertiesChangeStatus->value);
    }

    public function manageImages(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertiesManageImages->value);
    }

    public function viewImages(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyImagesView->value);
    }

    public function createImages(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyImagesCreate->value);
    }

    public function updateImages(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyImagesUpdate->value);
    }

    public function deleteImages(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyImagesDelete->value);
    }

    public function manageNotes(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertiesManageNotes->value);
    }

    public function viewNotes(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyNotesView->value);
    }

    public function createNotes(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyNotesCreate->value);
    }

    public function updateNotes(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyNotesUpdate->value);
    }

    public function deleteNotes(User $user, Property $property): bool
    {
        return $this->view($user, $property)
            && $user->can(PermissionName::PropertyNotesDelete->value);
    }

    public function assign(User $user, Property $property): bool
    {
        return $user->is_active
            && $user->agency_id === $property->agency_id
            && $user->can(PermissionName::PropertiesAssign->value);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->is_active
            && $user->agency_id === $property->agency_id
            && $user->can(PermissionName::PropertiesDelete->value);
    }

    public function restore(User $user, Property $property): bool
    {
        return $user->is_active
            && $user->roleName() === RoleName::AgencyManager
            && $user->agency_id === $property->agency_id
            && $user->can(PermissionName::PropertiesRestore->value);
    }
}
