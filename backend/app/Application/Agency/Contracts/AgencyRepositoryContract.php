<?php

declare(strict_types=1);

namespace App\Application\Agency\Contracts;

use App\Application\Agency\Data\ProvisionAgencyData;
use App\Models\Agency;

interface AgencyRepositoryContract
{
    public function create(ProvisionAgencyData $data): Agency;

    public function createSettings(Agency $agency, ProvisionAgencyData $data): void;

    public function hasActiveManager(Agency $agency): bool;

    public function save(Agency $agency): void;

    public function revokeAllMobileTokens(Agency $agency): void;
}
