<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Application\Owner\Services\DeleteOwnerService;
use App\Application\Property\Services\CreatePropertyService;
use App\Application\Property\Services\DeletePropertyService;
use App\Application\Property\Services\RestorePropertyService;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;
use App\Models\PropertyHistory;
use LogicException;
use Tests\Support\DomainTestCase;

final class DeletionAndHistoryTest extends DomainTestCase
{
    public function test_linked_owner_cannot_be_deleted_and_property_restore_revalidates_owner(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        $property = app(CreatePropertyService::class)->execute($manager, $this->salePropertyData($owner, $agent));

        try {
            app(DeleteOwnerService::class)->execute($owner);
            $this->fail('Linked owner deletion should fail.');
        } catch (DomainConflictException) {
            $this->assertFalse($owner->trashed());
        }

        app(DeletePropertyService::class)->execute($manager, $property);
        $owner->delete();
        $this->expectException(DomainConflictException::class);
        app(RestorePropertyService::class)->execute($manager, $property);
    }

    public function test_property_history_cannot_be_updated_or_deleted(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        $property = app(CreatePropertyService::class)->execute($manager, $this->salePropertyData($owner, $agent));
        $history = PropertyHistory::query()->where('property_id', $property->getKey())->firstOrFail();

        $this->expectException(LogicException::class);
        $history->delete();
    }
}
