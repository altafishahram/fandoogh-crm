<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Converted = 'converted';
    case Lost = 'lost';
}
