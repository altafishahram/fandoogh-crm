<?php

declare(strict_types=1);

namespace App\Filament\Control\Pages;

use App\Filament\Control\Widgets\PlatformDetails;
use App\Filament\Control\Widgets\PlatformStats;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'داشبورد مدیریت کل';

    public function getWidgets(): array
    {
        return [PlatformStats::class, PlatformDetails::class];
    }
}
