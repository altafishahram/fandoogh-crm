<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\MatchNotifications\Pages;

use App\Filament\Agency\Resources\MatchNotifications\MatchNotificationResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewMatchNotification extends ViewRecord
{
    protected static string $resource = MatchNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [MatchNotificationResource::markAsReadAction()];
    }
}
