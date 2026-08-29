<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;

final readonly class RestoreCustomerService
{
    public function __construct(
        private AgentAssignmentValidator $agents,
        private CustomerInvariantValidator $validator,
        private TenantContext $tenant,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(Customer $customer): Customer
    {
        $this->agents->validate((int) $customer->assigned_agent_id, $this->tenant->agencyId());

        if ($customer->status === CustomerStatus::Converted) {
            $property = Property::query()->find($customer->converted_property_id);
            $agent = $customer->assignedAgent;
            if (! $property instanceof Property) {
                throw new DomainConflictException('پیش از بازیابی مشتری، ملک نهایی‌شده باید فعال باشد.');
            }
            if (! $agent instanceof User) {
                throw new DomainConflictException('پیش از بازیابی مشتری، کارشناس مسئول باید موجود باشد.');
            }
            $this->validator->conversion($property, $customer->intent, $agent);
        }

        $customer->restore();
        $customer->matching_eligible_at = now();
        $customer->save();

        $this->matching->dispatchForAgency((int) $customer->agency_id);

        return $customer->refresh();
    }
}
