<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\PermissionName;
use App\Models\SavedFilter;
use App\Models\User;

final class SavedFilterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can(PermissionName::SavedFiltersManage->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SavedFilter $filter): bool
    {
        return $this->viewAny($user) && $filter->user_id === $user->getKey();
    }

    public function delete(User $user, SavedFilter $filter): bool
    {
        return $this->update($user, $filter);
    }
}
