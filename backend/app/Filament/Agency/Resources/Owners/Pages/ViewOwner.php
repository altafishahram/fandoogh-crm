<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Owners\Pages;

use App\Filament\Agency\Resources\Owners\OwnerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewOwner extends ViewRecord
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
