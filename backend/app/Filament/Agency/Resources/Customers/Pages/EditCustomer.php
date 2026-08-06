<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Customers\Pages;

use App\Filament\Agency\Resources\Customers\CustomerResource;
use App\Filament\Shared\Concerns\UpdatesCustomer;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditCustomer extends EditRecord
{
    use UpdatesCustomer;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
