<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Owners\Pages;

use App\Filament\Agency\Resources\Owners\OwnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOwners extends ListRecords
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
