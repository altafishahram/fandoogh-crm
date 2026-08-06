<?php

declare(strict_types=1);

namespace App\Filament\Agent\Widgets;

use App\Application\Dashboard\Services\DashboardService;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AgentStats extends StatsOverviewWidget
{
    /** @return list<Stat> */
    protected function getStats(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $data = app(DashboardService::class)->for($user);
        $properties = is_array($data['properties_by_status'] ?? null) ? $data['properties_by_status'] : [];

        return [
            Stat::make('املاک موجود', (int) ($properties['available'] ?? 0)),
            Stat::make('املاک رزروشده', (int) ($properties['reserved'] ?? 0)),
            Stat::make('معاملات نهایی‌شده', (int) ($properties['sold'] ?? 0) + (int) ($properties['rented'] ?? 0)),
            Stat::make('مشتریان فعال', (int) ($data['active_customers'] ?? 0)),
        ];
    }
}
