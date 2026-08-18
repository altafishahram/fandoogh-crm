<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case HouseVilla = 'house_villa';
    case Shop = 'shop';
    case Industrial = 'industrial';
    case LandOldBuilding = 'land_old_building';

    // Legacy values remain accepted during the API v1 compatibility window.
    case House = 'house';
    case Villa = 'villa';
    case Land = 'land';
    case Office = 'office';
    case Bureau = 'bureau';
    case Commercial = 'commercial';
    case Warehouse = 'warehouse';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<self> */
    public static function selectable(): array
    {
        return [self::Apartment, self::Office, self::House, self::Villa, self::LandOldBuilding, self::Bureau, self::Industrial];
    }
}
