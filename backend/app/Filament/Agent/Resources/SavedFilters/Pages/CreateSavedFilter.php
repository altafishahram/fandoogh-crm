<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\SavedFilters\Pages;

use App\Filament\Agent\Resources\SavedFilters\SavedFilterResource;
use App\Filament\Shared\Concerns\CreatesSavedFilter;
use Filament\Resources\Pages\CreateRecord;

final class CreateSavedFilter extends CreateRecord
{
    use CreatesSavedFilter;

    protected static string $resource = SavedFilterResource::class;
}
