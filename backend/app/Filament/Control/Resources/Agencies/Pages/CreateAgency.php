<?php

declare(strict_types=1);

namespace App\Filament\Control\Resources\Agencies\Pages;

use App\Application\Agency\Data\ProvisionAgencyData;
use App\Application\Agency\Services\ProvisionAgencyService;
use App\Application\Agency\Services\UpdateAgencyService;
use App\Filament\Control\Resources\Agencies\AgencyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        $agency = app(ProvisionAgencyService::class)->execute($actor, new ProvisionAgencyData(
            (string) $data['name'], (string) $data['slug'], (string) $data['email'], (string) $data['phone'],
            (string) $data['address_line_1'], isset($data['address_line_2']) ? (string) $data['address_line_2'] : null,
            (string) $data['city'], (string) $data['province'], isset($data['postal_code']) ? (string) $data['postal_code'] : null,
            mb_strtoupper((string) $data['country_code']), (string) $data['timezone'], (string) $data['locale'],
            mb_strtoupper((string) $data['currency_code']), mb_strtoupper((string) $data['property_code_prefix']),
            (int) $data['default_page_size'],
        ));
        if (! empty($data['city_id'])) {
            $agency = app(UpdateAgencyService::class)->execute($actor, $agency, $data);
        }

        return $agency;
    }
}
