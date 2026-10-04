<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\MatchNotifications\Pages;

use App\Filament\Agency\Resources\MatchNotifications\MatchNotificationResource;
use Filament\Resources\Pages\ListRecords;

final class ListMatchNotifications extends ListRecords
{
    protected static string $resource = MatchNotificationResource::class;
}
