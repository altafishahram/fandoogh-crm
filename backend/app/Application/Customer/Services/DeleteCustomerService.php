<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Models\Customer;

final class DeleteCustomerService
{
    public function __construct(private readonly MatchingRebuildDispatcher $matching) {}

    public function execute(Customer $customer): void
    {
        $customer->delete();
        $this->matching->dispatchForAgency((int) $customer->agency_id);
    }
}
