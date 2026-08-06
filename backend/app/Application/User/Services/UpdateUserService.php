<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class UpdateUserService
{
    /** @param array{name: string, email: string, phone?: string|null} $attributes */
    public function execute(User $actor, User $target, array $attributes): User
    {
        $canManage = match ($actor->roleName()) {
            RoleName::SuperAdmin => $target->roleName() === RoleName::AgencyManager,
            RoleName::AgencyManager => $target->roleName() === RoleName::Agent
                && $actor->agency_id === $target->agency_id,
            RoleName::Agent => false,
        };
        if (! $actor->is_active || ! $actor->can(PermissionName::UsersUpdate->value) || ! $canManage) {
            throw new AuthorizationException;
        }

        $target->fill($attributes)->save();

        return $target->refresh();
    }
}
