<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DeactivateUserService
{
    public function __construct(private UserIdentityRepositoryContract $users) {}

    public function execute(User $actor, User $target): void
    {
        if ($actor->is($target)
            || ! $actor->is_active
            || ! $actor->can(PermissionName::UsersDeactivate->value)
            || ! $this->canManage($actor, $target)) {
            throw new AuthorizationException;
        }

        $target->is_active = false;
        $this->users->save($target);
        $this->users->revokeAllMobileTokens($target);
    }

    private function canManage(User $actor, User $target): bool
    {
        return match ($actor->roleName()) {
            RoleName::SuperAdmin => $target->roleName() === RoleName::AgencyManager,
            RoleName::AgencyManager => $target->roleName() === RoleName::Agent
                && $actor->agency_id === $target->agency_id,
            RoleName::Agent => false,
        };
    }
}
