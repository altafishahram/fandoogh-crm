<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Application\User\Data\CreateUserData;
use App\Models\Agency;
use App\Models\User;

final class EloquentUserIdentityRepository implements UserIdentityRepositoryContract
{
    public function findForAuthentication(string $normalizedEmail): ?User
    {
        return User::query()->where('email', $normalizedEmail)->first();
    }

    public function create(CreateUserData $data, ?Agency $agency): User
    {
        $user = new User([
            'name' => trim($data->name),
            'email' => mb_strtolower(trim($data->email)),
            'phone' => $data->phone === null ? null : trim($data->phone),
            'password' => $data->temporaryPassword,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        if ($agency !== null) {
            $user->agency()->associate($agency);
        }

        $user->save();

        return $user;
    }

    public function save(User $user): void
    {
        $user->save();
    }

    public function revokeAllMobileTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function revokeExpiredMobileTokens(User $user): void
    {
        $user->tokens()->where('expires_at', '<=', now())->delete();
    }
}
