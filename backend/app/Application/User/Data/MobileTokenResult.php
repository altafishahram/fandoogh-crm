<?php

declare(strict_types=1);

namespace App\Application\User\Data;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class MobileTokenResult
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
        public CarbonImmutable $expiresAt,
    ) {}
}
