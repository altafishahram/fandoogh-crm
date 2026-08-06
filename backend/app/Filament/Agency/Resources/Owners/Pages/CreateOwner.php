<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Owners\Pages;

use App\Filament\Agency\Resources\Owners\OwnerResource;
use App\Filament\Shared\Concerns\CreatesOwner;
use Filament\Resources\Pages\CreateRecord;

final class CreateOwner extends CreateRecord
{
    use CreatesOwner;

    protected static string $resource = OwnerResource::class;
}
