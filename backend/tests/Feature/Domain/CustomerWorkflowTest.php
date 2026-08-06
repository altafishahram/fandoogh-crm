<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Customer\Services\CreateCustomerService;
use App\Application\Customer\Services\UpdateCustomerService;
use App\Application\Property\Services\CreatePropertyService;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;
use Tests\Support\DomainTestCase;

final class CustomerWorkflowTest extends DomainTestCase
{
    public function test_agent_created_customer_is_assigned_to_actor_and_can_convert_to_compatible_property(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($agent);
        $owner = Owner::factory()->forAgency($agency, $agent)->create();
        $property = app(CreatePropertyService::class)->execute($agent, $this->salePropertyData($owner, $manager));
        $customer = app(CreateCustomerService::class)->execute($agent, $this->customerData((int) $manager->getKey()));

        $this->assertSame($agent->getKey(), $customer->assigned_agent_id);
        $converted = app(UpdateCustomerService::class)->execute($agent, $customer, new UpdateCustomerData([
            'status' => CustomerStatus::Converted,
            'converted_property_id' => $property->getKey(),
        ], $this->updatedAt($customer)));

        $this->assertSame(CustomerStatus::Converted, $converted->status);
        $this->assertSame($property->getKey(), $converted->converted_property_id);

        $this->expectException(DomainConflictException::class);
        app(UpdateCustomerService::class)->execute($agent, $converted, new UpdateCustomerData([
            'status' => CustomerStatus::Active,
        ], $this->updatedAt($converted)));
    }

    public function test_customer_rejects_invalid_ranges(): void
    {
        ['agent' => $agent] = $this->tenant();
        $this->establish($agent);
        $data = $this->customerData((int) $agent->getKey(), '500.00', '100.00');

        $this->expectException(DomainConflictException::class);
        app(CreateCustomerService::class)->execute($agent, $data);
    }

    private function customerData(
        int $assignedAgentId,
        ?string $budgetMin = null,
        ?string $budgetMax = null,
    ): CreateCustomerData {
        return new CreateCustomerData(
            $assignedAgentId, 'Sara', 'Karimi', '+989121111111', null, 'sara@example.test',
            PreferredContactMethod::Phone, CustomerIntent::Buy, ['apartment'],
            $budgetMin, $budgetMax, 'Tehran', null, null, null, 2,
        );
    }
}
