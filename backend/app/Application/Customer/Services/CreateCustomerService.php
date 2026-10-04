<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Geography\Services\LocationValidator;
use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Domain\Customer\Enums\CustomerHistoryAction;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;

final readonly class CreateCustomerService
{
    public function __construct(
        private CustomerRepositoryContract $customers,
        private CustomerInvariantValidator $validator,
        private AgentAssignmentValidator $agents,
        private TenantContext $tenant,
        private CustomerHistoryWriter $history,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(User $actor, CreateCustomerData $data): Customer
    {
        $attributes = app(LocationValidator::class)->normalize($data->attributes(), 'desired_');
        $this->validator->validate($attributes);
        $agentId = $data->fullName !== null
            ? null
            : ($actor->roleName() === RoleName::Agent ? (int) $actor->getKey() : $data->assignedAgentId);
        if ($agentId !== null) {
            $this->agents->validate($agentId, $this->tenant->agencyId());
        }

        $customer = $this->customers->create($attributes + [
            'assigned_agent_id' => $agentId,
            'created_by_user_id' => $actor->getKey(),
            'created_by_role' => $actor->roleName()->value,
            'status' => CustomerStatus::Active,
            'converted_property_id' => null,
            'lock_version' => 1,
            'matching_eligible_at' => now(),
        ]);

        $this->history->write($customer, $actor, CustomerHistoryAction::Created);

        $this->matching->dispatchForAgency((int) $customer->agency_id);

        return $customer;
    }
}
