<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Villa = 'villa';
    case Land = 'land';
    case Office = 'office';
    case Commercial = 'commercial';
    case Warehouse = 'warehouse';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
