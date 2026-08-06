<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Models\Customer;

final class EloquentCustomerRepository implements CustomerRepositoryContract
{
    public function create(array $attributes): Customer
    {
        return Customer::query()->create($attributes);
    }

    public function save(Customer $customer): Customer
    {
        $customer->save();

        return $customer->refresh();
    }

    public function lock(int $customerId): Customer
    {
        return Customer::query()->lockForUpdate()->findOrFail($customerId);
    }
}
