<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Finalized = 'finalized';
    case Withdrawn = 'withdrawn';
    case TransactedElsewhere = 'transacted_elsewhere';

    // Legacy values remain accepted during the API v1 compatibility window.
    case Inactive = 'inactive';
    case Converted = 'converted';
    case Lost = 'lost';
}
