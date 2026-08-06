<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ResetUserPasswordService
{
    public function __construct(private UserIdentityRepositoryContract $users) {}

    public function execute(User $actor, User $target, string $temporaryPassword): void
    {
        if (! $actor->is_active
            || ! $actor->can(PermissionName::UsersResetPassword->value)
            || ! $this->canManage($actor, $target)) {
            throw new AuthorizationException;
        }

        $target->password = $temporaryPassword;
        $target->must_change_password = true;
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
