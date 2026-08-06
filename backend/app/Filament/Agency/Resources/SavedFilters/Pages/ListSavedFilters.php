<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\SavedFilters\Pages;

use App\Filament\Agency\Resources\SavedFilters\SavedFilterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSavedFilters extends ListRecords
{
    protected static string $resource = SavedFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
