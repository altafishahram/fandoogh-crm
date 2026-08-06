<?php

declare(strict_types=1);

namespace App\Domain\SavedFilter\Enums;

enum FilterModule: string
{
    case Properties = 'properties';
    case Owners = 'owners';
    case Customers = 'customers';
}
