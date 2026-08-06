<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\SavedFilters\Pages;

use App\Filament\Agency\Resources\SavedFilters\SavedFilterResource;
use App\Filament\Shared\Concerns\UpdatesSavedFilter;
use Filament\Resources\Pages\EditRecord;

final class EditSavedFilter extends EditRecord
{
    use UpdatesSavedFilter;

    protected static string $resource = SavedFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
