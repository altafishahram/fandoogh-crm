<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Properties\Pages;

use App\Filament\Agency\Resources\Properties\PropertyResource;
use App\Filament\Shared\Concerns\CreatesProperty;
use Filament\Resources\Pages\CreateRecord;

final class CreateProperty extends CreateRecord
{
    use CreatesProperty;

    protected static string $resource = PropertyResource::class;
}
