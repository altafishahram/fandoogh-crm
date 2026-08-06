<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Tenancy\TenantContext;
use App\Models\Agency;
use App\Models\Owner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

abstract class DomainTestCase extends IdentityTestCase
{
    /** @return array{agency: Agency, manager: User, agent: User} */
    protected function tenant(): array
    {
        $agency = Agency::factory()->active()->create();
        $manager = User::factory()->agencyManager($agency)->create();
        $agent = User::factory()->agent($agency)->create();

        return compact('agency', 'manager', 'agent');
    }

    protected function establish(User $user): void
    {
        app(TenantContext::class)->establish($user);
    }

    protected function updatedAt(Model $model): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $model->getAttribute('updated_at'));
    }

    /** @param list<PropertyOwnershipData>|null $ownerships */
    protected function salePropertyData(
        Owner $owner,
        ?User $agent = null,
        ?array $ownerships = null,
    ): CreatePropertyData {
        return new CreatePropertyData(
            title: 'Two bedroom apartment',
            description: 'Internal listing description.',
            propertyType: PropertyType::Apartment,
            transactionType: TransactionType::Sale,
            assignedAgentId: $agent === null ? null : (int) $agent->getKey(),
            salePrice: '2500000.00',
            depositAmount: null,
            monthlyRent: null,
            areaSqm: '95.50',
            bedrooms: 2,
            bathrooms: 1,
            floorNumber: 2,
            totalFloors: 5,
            yearBuilt: 2020,
            parkingSpaces: 1,
            hasStorageRoom: true,
            hasElevator: true,
            hasBalcony: false,
            city: 'Tehran',
            district: 'District 1',
            streetAddress: 'Test street 10',
            postalCode: '1234567890',
            latitude: null,
            longitude: null,
            availableFrom: null,
            owners: $ownerships ?? [new PropertyOwnershipData((int) $owner->getKey(), '100.00', true)],
        );
    }
}
