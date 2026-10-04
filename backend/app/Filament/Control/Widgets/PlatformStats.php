<?php

declare(strict_types=1);

namespace App\Filament\Control\Widgets;

use App\Application\Dashboard\Services\DashboardService;
use App\Filament\Control\Support\PlatformPanelAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PlatformStats extends StatsOverviewWidget
{
    /** @return list<Stat> */
    protected function getStats(): array
    {
        $user = PlatformPanelAccess::actor();
        $data = app(DashboardService::class)->for($user);
        $agencies = is_array($data['agencies'] ?? null) ? $data['agencies'] : [];
        $users = is_array($data['users'] ?? null) ? $data['users'] : [];

        return [
            Stat::make('آژانس‌های فعال', (int) ($agencies['active'] ?? 0)),
            Stat::make('آژانس‌های تعلیق‌شده', (int) ($agencies['suspended'] ?? 0)),
            Stat::make('مدیران فعال', (int) ($users['managers'] ?? 0)),
            Stat::make('کارشناسان فعال', (int) ($users['agents'] ?? 0)),
        ];
    }
}
