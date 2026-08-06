<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Application\User\Data\CreateUserData;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserService
{
    public function __construct(private UserIdentityRepositoryContract $users) {}

    public function execute(User $actor, Agency $agency, CreateUserData $data): User
    {
        if (! $actor->is_active || ! $actor->can(PermissionName::UsersCreate->value)) {
            throw new AuthorizationException;
        }

        $actorRole = $actor->roleName();
        $isPlatformManagerCreation = $actorRole === RoleName::SuperAdmin
            && $data->role === RoleName::AgencyManager;
        $isTenantAgentCreation = $actorRole === RoleName::AgencyManager
            && $data->role === RoleName::Agent
            && $actor->agency_id === $agency->getKey()
            && $agency->is_active;

        if (! $isPlatformManagerCreation && ! $isTenantAgentCreation) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($agency, $data): User {
            $user = $this->users->create($data, $agency);
            $user->syncRoles([$data->role->value]);

            return $user;
        });
    }
}
