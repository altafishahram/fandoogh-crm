<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum TransactionType: string
{
    case Sale = 'sale';
    case Rent = 'rent';
}
