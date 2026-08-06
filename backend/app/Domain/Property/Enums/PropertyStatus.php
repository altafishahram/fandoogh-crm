<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum PropertyStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Rented = 'rented';
    case Archived = 'archived';

    public function isReadOnly(): bool
    {
        return in_array($this, [self::Sold, self::Rented, self::Archived], true);
    }
}
