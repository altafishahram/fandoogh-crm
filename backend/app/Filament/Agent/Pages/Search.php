<?php

declare(strict_types=1);

namespace App\Filament\Agent\Pages;

use App\Filament\Shared\Pages\SearchPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class Search extends SearchPage
{
    protected static ?string $title = 'جست‌وجوی سراسری';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;
}
