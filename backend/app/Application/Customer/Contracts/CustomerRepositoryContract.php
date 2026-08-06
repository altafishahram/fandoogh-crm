<?php

declare(strict_types=1);

namespace App\Application\Customer\Contracts;

use App\Models\Customer;

interface CustomerRepositoryContract
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Customer;

    public function save(Customer $customer): Customer;

    public function lock(int $customerId): Customer;
}
