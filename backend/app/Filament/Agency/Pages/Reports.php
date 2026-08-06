<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Filament\Shared\Pages\ReportPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class Reports extends ReportPage
{
    protected static ?string $title = 'گزارش‌های آژانس';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
}
