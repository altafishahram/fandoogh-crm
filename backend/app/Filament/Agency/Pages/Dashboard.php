<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Filament\Agency\Widgets\AgencyDetails;
use App\Filament\Agency\Widgets\AgencyStats;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'داشبورد آژانس';

    public function getWidgets(): array
    {
        return [AgencyStats::class, AgencyDetails::class];
    }
}
