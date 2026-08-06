<?php

declare(strict_types=1);

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Widgets\AgentDetails;
use App\Filament\Agent\Widgets\AgentStats;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'داشبورد من';

    public function getWidgets(): array
    {
        return [AgentStats::class, AgentDetails::class];
    }
}
