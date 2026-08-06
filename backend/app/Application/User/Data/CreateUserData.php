<?php

declare(strict_types=1);

namespace App\Application\User\Data;

use App\Domain\User\Enums\RoleName;

final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $temporaryPassword,
        public RoleName $role,
    ) {}
}
