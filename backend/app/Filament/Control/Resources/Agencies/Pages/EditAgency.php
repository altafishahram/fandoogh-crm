<?php

declare(strict_types=1);

namespace App\Filament\Control\Resources\Agencies\Pages;

use App\Application\Agency\Services\UpdateAgencyService;
use App\Application\Agency\Services\UpdateAgencySettingsService;
use App\Domain\Tenancy\AgencyScope;
use App\Filament\Control\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Models\AgencySettings;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof Agency) {
            $settings = AgencySettings::withoutGlobalScope(AgencyScope::class)
                ->where('agency_id', $record->getKey())->firstOrFail();
            $data['property_code_prefix'] = $settings->property_code_prefix;
            $data['default_page_size'] = $settings->default_page_size;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Agency, 401);

        return DB::transaction(function () use ($actor, $record, $data): Agency {
            $agency = app(UpdateAgencyService::class)->execute($actor, $record, $data);
            app(UpdateAgencySettingsService::class)->execute(
                $actor, $agency, (string) $data['property_code_prefix'], (int) $data['default_page_size'],
            );

            return $agency->refresh();
        });
    }
}
