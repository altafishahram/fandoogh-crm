<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\Customers\Pages;

use App\Filament\Agent\Resources\Customers\CustomerResource;
use App\Filament\Shared\Concerns\CreatesCustomer;
use Filament\Resources\Pages\CreateRecord;

final class CreateCustomer extends CreateRecord
{
    use CreatesCustomer;

    protected static string $resource = CustomerResource::class;
}
