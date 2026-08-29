<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\MatchNotifications;

use App\Filament\Agent\Resources\Customers\CustomerResource;
use App\Filament\Agent\Resources\MatchNotifications\Pages\ListMatchNotifications;
use App\Filament\Agent\Resources\MatchNotifications\Pages\ViewMatchNotification;
use App\Filament\Agent\Resources\Properties\PropertyResource;
use App\Filament\Shared\Resources\MatchNotifications\MatchNotificationResourceBase;

final class MatchNotificationResource extends MatchNotificationResourceBase
{
    protected static bool $shouldRegisterNavigation = true;

    protected static function propertyResourceClass(): string
    {
        return PropertyResource::class;
    }

    protected static function customerResourceClass(): string
    {
        return CustomerResource::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatchNotifications::route('/'),
            'view' => ViewMatchNotification::route('/{record}'),
        ];
    }
}
