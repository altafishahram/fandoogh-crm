<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

enum CustomerHistoryAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
}
