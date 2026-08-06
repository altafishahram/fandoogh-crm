<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

use App\Domain\Property\Enums\TransactionType;

enum CustomerIntent: string
{
    case Buy = 'buy';
    case Rent = 'rent';

    public function transactionType(): TransactionType
    {
        return match ($this) {
            self::Buy => TransactionType::Sale,
            self::Rent => TransactionType::Rent,
        };
    }
}
