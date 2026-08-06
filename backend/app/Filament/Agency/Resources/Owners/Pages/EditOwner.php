<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Owners\Pages;

use App\Filament\Agency\Resources\Owners\OwnerResource;
use App\Filament\Shared\Concerns\UpdatesOwner;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditOwner extends EditRecord
{
    use UpdatesOwner;

    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
