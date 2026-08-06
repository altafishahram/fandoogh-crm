<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\SavedFilter\Services\SavedFilterService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CreatesSavedFilter
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return app(SavedFilterService::class)->create($actor, PanelDataMapper::savedFilter($data));
    }
}
