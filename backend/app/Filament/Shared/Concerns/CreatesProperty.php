<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Property\Services\CreatePropertyService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CreatesProperty
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return app(CreatePropertyService::class)->execute($actor, PanelDataMapper::propertyCreate($data));
    }
}
