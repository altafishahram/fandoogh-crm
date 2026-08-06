<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Shared\Services\AgentAssignmentValidator;
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
    ) {}

    public function execute(User $actor, CreateCustomerData $data): Customer
    {
        $attributes = $data->attributes();
        $this->validator->validate($attributes);
        $agentId = $actor->roleName() === RoleName::Agent
            ? (int) $actor->getKey()
            : $data->assignedAgentId;
        $this->agents->validate($agentId, $this->tenant->agencyId());

        return $this->customers->create($attributes + [
            'assigned_agent_id' => $agentId,
            'created_by_user_id' => $actor->getKey(),
            'status' => CustomerStatus::Active,
            'converted_property_id' => null,
        ]);
    }
}
