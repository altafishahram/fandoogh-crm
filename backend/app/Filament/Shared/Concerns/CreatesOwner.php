<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Owner\Services\CreateOwnerService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CreatesOwner
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return app(CreateOwnerService::class)->execute($actor, PanelDataMapper::owner($data));
    }
}
