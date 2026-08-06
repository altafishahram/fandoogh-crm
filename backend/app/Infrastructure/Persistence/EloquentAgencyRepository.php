<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Agency\Contracts\AgencyRepositoryContract;
use App\Application\Agency\Data\ProvisionAgencyData;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\AgencySettings;
use App\Models\User;

final class EloquentAgencyRepository implements AgencyRepositoryContract
{
    public function create(ProvisionAgencyData $data): Agency
    {
        return Agency::query()->create([
            'name' => trim($data->name),
            'slug' => mb_strtolower(trim($data->slug)),
            'email' => mb_strtolower(trim($data->email)),
            'phone' => trim($data->phone),
            'address_line_1' => trim($data->addressLine1),
            'address_line_2' => $data->addressLine2,
            'city' => trim($data->city),
            'province' => trim($data->province),
            'postal_code' => $data->postalCode,
            'country_code' => mb_strtoupper($data->countryCode),
            'timezone' => $data->timezone,
            'locale' => $data->locale,
            'currency_code' => mb_strtoupper($data->currencyCode),
            'is_active' => false,
        ]);
    }

    public function createSettings(Agency $agency, ProvisionAgencyData $data): void
    {
        $settings = new AgencySettings([
            'property_code_prefix' => mb_strtoupper(trim($data->propertyCodePrefix)),
            'next_property_sequence' => 1,
            'default_page_size' => $data->defaultPageSize,
        ]);
        $settings->agency()->associate($agency);
        $settings->save();
    }

    public function hasActiveManager(Agency $agency): bool
    {
        return User::query()
            ->where('agency_id', $agency->getKey())
            ->where('is_active', true)
            ->role(RoleName::AgencyManager->value)
            ->exists();
    }

    public function save(Agency $agency): void
    {
        $agency->save();
    }

    public function revokeAllMobileTokens(Agency $agency): void
    {
        User::query()
            ->where('agency_id', $agency->getKey())
            ->eachById(static fn (User $user) => $user->tokens()->delete());
    }
}
