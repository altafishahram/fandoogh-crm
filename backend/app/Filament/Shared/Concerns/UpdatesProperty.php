<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Property\Services\SyncPropertyOwnersService;
use App\Application\Property\Services\UpdatePropertyService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

trait UpdatesProperty
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof Property) {
            $data['owners'] = $record->owners()->get()->map(static function ($owner): array {
                $pivot = $owner->getRelation('pivot');
                abort_unless($pivot instanceof Pivot, 500);

                return [
                    'owner_id' => $owner->getKey(),
                    'ownership_percentage' => $pivot->getAttribute('ownership_percentage'),
                    'is_primary' => (bool) $pivot->getAttribute('is_primary'),
                ];
            })->all();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Property, 401);
        $expected = CarbonImmutable::parse((string) $record->updated_at);
        $updated = app(UpdatePropertyService::class)->execute(
            $actor, $record, PanelDataMapper::propertyUpdate($data, $expected),
        );
        app(SyncPropertyOwnersService::class)->execute($actor, $updated, PanelDataMapper::ownerships($data));

        return $updated->refresh();
    }
}
