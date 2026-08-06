<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Owner\Services\UpdateOwnerService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\Owner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

trait UpdatesOwner
{
    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Owner, 401);

        return app(UpdateOwnerService::class)->execute(
            $actor, $record, PanelDataMapper::owner($data), CarbonImmutable::parse((string) $record->updated_at),
        );
    }
}
