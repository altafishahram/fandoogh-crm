<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

enum PreferredContactMethod: string
{
    case Phone = 'phone';
    case Email = 'email';
}
