<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Models\Customer;

final class DeleteCustomerService
{
    public function execute(Customer $customer): void
    {
        $customer->delete();
    }
}
