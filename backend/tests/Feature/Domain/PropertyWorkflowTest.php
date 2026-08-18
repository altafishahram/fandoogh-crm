<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\CreatePropertyService;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;
use Illuminate\Support\Facades\DB;
use Tests\Support\DomainTestCase;

final class PropertyWorkflowTest extends DomainTestCase
{
    public function test_property_creation_consumes_sequence_links_owners_and_writes_history(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        $secondOwner = Owner::factory()->forAgency($agency, $manager)->create();
        $service = app(CreatePropertyService::class);

        $first = $service->execute($manager, $this->salePropertyData($owner, $agent));
        $second = $service->execute($manager, $this->salePropertyData($secondOwner, $agent));

        $prefix = $agency->settings()->firstOrFail()->property_code_prefix;
        $this->assertSame($prefix.'-000001', $first->code);
        $this->assertSame($prefix.'-000002', $second->code);
        $ownership = DB::table('property_owner')->where('property_id', $first->getKey());
        $this->assertSame('100.00', $ownership->value('ownership_percentage'));
        $this->assertTrue((bool) $ownership->value('is_primary'));
        $this->assertDatabaseHas('property_histories', [
            'property_id' => $first->getKey(), 'action' => PropertyHistoryAction::Created->value,
        ]);
        $this->assertSame(3, $agency->settings()->firstOrFail()->next_property_sequence);
    }

    public function test_property_creation_rejects_invalid_ownership_sets(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $ownerOne = Owner::factory()->forAgency($agency, $manager)->create();
        $ownerTwo = Owner::factory()->forAgency($agency, $manager)->create();
        $data = $this->salePropertyData($ownerOne, $agent, [
            new PropertyOwnershipData((int) $ownerOne->getKey(), '60.00', true),
            new PropertyOwnershipData((int) $ownerTwo->getKey(), '30.00', false),
        ]);

        $this->expectException(DomainConflictException::class);
        app(CreatePropertyService::class)->execute($manager, $data);
    }

    public function test_status_machine_requires_reasons_and_writes_immutable_history(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        $property = app(CreatePropertyService::class)->execute($manager, $this->salePropertyData($owner, $agent));
        $service = app(ChangePropertyStatusService::class);

        $reserved = $service->execute($manager, $property, new ChangePropertyStatusData(
            PropertyStatus::Reserved, null, null, $this->updatedAt($property),
        ));
        $this->assertDatabaseHas('property_histories', [
            'property_id' => $property->getKey(),
            'action' => PropertyHistoryAction::StatusChanged->value,
            'from_status' => PropertyStatus::Available->value,
            'to_status' => PropertyStatus::Reserved->value,
        ]);

        $this->expectException(DomainConflictException::class);
        $service->execute($manager, $reserved, new ChangePropertyStatusData(
            PropertyStatus::Available, null, null, $this->updatedAt($reserved),
        ));
    }
}
