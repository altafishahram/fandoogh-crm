<?php

declare(strict_types=1);

namespace App\Domain\Owner\Enums;

enum OwnerType: string
{
    case Person = 'person';
    case Company = 'company';
}
