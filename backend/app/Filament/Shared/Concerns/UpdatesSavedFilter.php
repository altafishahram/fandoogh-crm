<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\SavedFilter\Services\SavedFilterService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait UpdatesSavedFilter
{
    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof SavedFilter, 401);

        return app(SavedFilterService::class)->update($actor, $record, PanelDataMapper::savedFilter($data));
    }
}
