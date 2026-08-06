<?php

declare(strict_types=1);

namespace App\Filament\Control\Resources\Agencies\Pages;

use App\Filament\Control\Resources\Agencies\AgencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
