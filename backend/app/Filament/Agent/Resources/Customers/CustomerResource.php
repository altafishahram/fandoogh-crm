<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\Customers;

use App\Filament\Agent\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Agent\Resources\Customers\Pages\EditCustomer;
use App\Filament\Agent\Resources\Customers\Pages\ListCustomers;
use App\Filament\Agent\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Shared\Resources\Customers\CustomerResourceBase;

final class CustomerResource extends CustomerResourceBase
{
    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
