<?php

declare(strict_types=1);

namespace App\Application\User\Contracts;

use App\Application\User\Data\CreateUserData;
use App\Models\Agency;
use App\Models\User;

interface UserIdentityRepositoryContract
{
    public function findForAuthentication(string $normalizedEmail): ?User;

    public function create(CreateUserData $data, ?Agency $agency): User;

    public function save(User $user): void;

    public function revokeAllMobileTokens(User $user): void;

    public function revokeExpiredMobileTokens(User $user): void;
}
