<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\Properties;

use App\Filament\Agent\Resources\Properties\Pages\CreateProperty;
use App\Filament\Agent\Resources\Properties\Pages\EditProperty;
use App\Filament\Agent\Resources\Properties\Pages\ListProperties;
use App\Filament\Agent\Resources\Properties\Pages\ViewProperty;
use App\Filament\Shared\Resources\Properties\PropertyResourceBase;

final class PropertyResource extends PropertyResourceBase
{
    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'view' => ViewProperty::route('/{record}'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }
}
