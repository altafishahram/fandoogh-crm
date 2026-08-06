<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\SavedFilters\Pages;

use App\Filament\Agent\Resources\SavedFilters\SavedFilterResource;
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
