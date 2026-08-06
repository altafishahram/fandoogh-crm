<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Properties\Pages;

use App\Filament\Agency\Resources\Properties\PropertyResource;
use App\Filament\Shared\Concerns\UpdatesProperty;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditProperty extends EditRecord
{
    use UpdatesProperty;

    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
