<?php

declare(strict_types=1);

namespace App\Filament\Agent\Pages;

use App\Filament\Shared\Pages\ReportPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class Reports extends ReportPage
{
    protected static ?string $title = 'گزارش من';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
}
