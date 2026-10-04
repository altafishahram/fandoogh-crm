<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Application\Customer\Data\CreateCustomerData;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Customer\Services\CreateCustomerService;
use App\Application\Customer\Services\CustomerInvariantValidator;
use App\Application\Customer\Services\UpdateCustomerService;
use App\Application\Matching\Services\MarkMatchNotificationReadService;
use App\Application\Matching\Services\PropertyCustomerMatchingService;
use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Property\Data\UpdatePropertyData;
use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\CreatePropertyService;
use App\Application\Property\Services\UpdatePropertyService;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Matching\Enums\MatchMode;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\RebuildAgencyPropertyCustomerMatches;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainTestCase;

final class PropertyCustomerMatchingTest extends DomainTestCase
{
    public function test_existing_sale_score_and_fingerprint_are_unchanged_by_ranking(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent)->refresh();
        $customer = $this->customer($agency, $manager, $agent)->refresh();
        $service = app(PropertyCustomerMatchingService::class);
        $candidate = $service->calculate($property, $customer);

        self::assertNotNull($candidate);
        self::assertSame(42.5, $candidate->score);
        self::assertSame(30.0, $candidate->financialScore);
        self::assertSame(12.5, $candidate->areaScore);
        self::assertSame(0.0, $candidate->featureScore);
        self::assertSame(MatchMode::Direct, $candidate->mode);

        $service->rebuildForAgency((int) $agency->getKey());
        $match = PropertyCustomerMatch::query()->sole();
        self::assertSame($candidate->fingerprint, $match->fingerprint);
        self::assertSame('42.50', $match->score);
        self::assertSame('30.00', $match->financial_score);
        self::assertSame('12.50', $match->area_score);
        self::assertSame('0.00', $match->feature_score);
    }

    /** @param array<string, mixed> $propertyChanges
     * @param  array<string, mixed>  $customerChanges
     */
    #[DataProvider('disqualifyingChanges')]
    public function test_top_twenty_does_not_relax_existing_matching_conditions(array $propertyChanges, array $customerChanges): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent)->forceFill($propertyChanges);
        $customer = $this->customer($agency, $manager, $agent)->forceFill($customerChanges);

        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($property, $customer));
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function disqualifyingChanges(): array
    {
        return [
            'old property' => [['matching_eligible_at' => null], []],
            'old customer' => [[], ['matching_eligible_at' => null]],
            'unavailable property' => [['status' => PropertyStatus::Reserved], []],
            'inactive customer' => [[], ['status' => CustomerStatus::Withdrawn]],
            'different property type' => [[], ['desired_property_type' => PropertyType::Industrial->value]],
            'wrong transaction intent' => [[], ['intent' => CustomerIntent::Rent]],
            'different city' => [[], ['desired_city' => 'Shiraz']],
            'different district' => [[], ['desired_district' => 'District 2']],
            'outside financial range' => [[], ['budget_max' => '4500000.00']],
            'missing financial range' => [[], ['budget_max' => null]],
            'outside area range' => [[], ['min_area_sqm' => '105.00']],
            'missing area range' => [[], ['max_area_sqm' => null]],
            'insufficient bedrooms' => [[], ['min_bedrooms' => 3]],
            'insufficient parking' => [[], ['min_parking_spaces' => 2]],
            'missing required storage' => [['has_storage_room' => false], ['has_storage_room' => true]],
            'missing required elevator' => [['has_elevator' => false], ['has_elevator' => true]],
            'missing required balcony' => [['has_balcony' => false], ['has_balcony' => true]],
        ];
    }

    public function test_direct_sale_match_is_scoped_to_one_agency_and_ignores_old_records(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);

        $property = $this->property($agency, $manager, $agent);
        $customer = $this->customer($agency, $manager, $agent);
        $oldCustomer = $this->customer($agency, $manager, $agent, [
            'matching_eligible_at' => null,
        ]);

        app(TenantContext::class)->clear();
        $otherTenant = $this->tenant();
        $this->establish($otherTenant['manager']);
        $otherCustomer = $this->customer($otherTenant['agency'], $otherTenant['manager'], $otherTenant['agent']);

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        $this->assertDatabaseHas('property_customer_matches', [
            'agency_id' => $agency->getKey(),
            'property_id' => $property->getKey(),
            'customer_id' => $customer->getKey(),
            'match_mode' => MatchMode::Direct->value,
        ]);
        $this->assertDatabaseMissing('property_customer_matches', [
            'agency_id' => $agency->getKey(),
            'property_id' => $property->getKey(),
            'customer_id' => $oldCustomer->getKey(),
        ]);
        $this->assertNull(app(PropertyCustomerMatchingService::class)->calculate($property, $otherCustomer));
        $this->assertDatabaseHas('match_notifications', [
            'agency_id' => $agency->getKey(),
        ]);
    }

    public function test_rebuild_removes_disqualified_matches_and_updates_one_notification(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        $customer = $this->customer($agency, $manager, $agent);
        $service = app(PropertyCustomerMatchingService::class);

        $service->rebuildForAgency((int) $agency->getKey());
        $match = PropertyCustomerMatch::query()->firstOrFail();
        $notification = MatchNotification::query()->firstOrFail();
        $notificationId = $notification->getKey();

        $property->forceFill([
            'matching_eligible_at' => now()->addSecond(),
            'sale_price' => '5500000.00',
        ]);
        $property->save();
        $service->rebuildForAgency((int) $agency->getKey());

        $this->assertSame($match->getKey(), PropertyCustomerMatch::query()->firstOrFail()->getKey());
        $this->assertSame($notificationId, MatchNotification::query()->firstOrFail()->getKey());
        $this->assertSame(2, (int) MatchNotification::query()->firstOrFail()->version);
        $this->assertSame(1, MatchNotification::query()->count());

        $property->status = PropertyStatus::Reserved;
        $property->save();
        $service->rebuildForAgency((int) $agency->getKey());

        $this->assertSame(0, PropertyCustomerMatch::query()->count());
        $this->assertSame(0, MatchNotification::query()->count());
        $this->assertNotNull($customer->refresh());
    }

    public function test_rent_conversion_matches_a_real_range_and_rejects_rent_only_values(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent, [
            'transaction_type' => TransactionType::Rent,
            'sale_price' => null,
            'deposit_amount' => '100000000.00',
            'monthly_rent' => '0.00',
            'is_convertible' => true,
            'minimum_deposit' => '50000000.00',
        ]);
        $customer = $this->customer($agency, $manager, $agent, [
            'intent' => CustomerIntent::Rent,
            'budget_min' => null,
            'budget_max' => null,
            'rental_deposit_min' => '50000000.00',
            'rental_deposit_max' => '75000000.00',
            'rental_rent_min' => '750000.00',
            'rental_rent_max' => '1500000.00',
            'accepts_rent_conversion' => true,
        ]);

        $candidate = app(PropertyCustomerMatchingService::class)->calculate($property, $customer);

        self::assertNotNull($candidate);
        self::assertSame(MatchMode::Converted, $candidate->mode);
        self::assertEqualsWithDelta(50000000.0, $candidate->matchedDepositMin, 0.01);
        self::assertEqualsWithDelta(75000000.0, $candidate->matchedDepositMax, 0.01);
        self::assertEqualsWithDelta(750000.0, $candidate->matchedRentMin, 0.01);
        self::assertEqualsWithDelta(1500000.0, $candidate->matchedRentMax, 0.01);

        $property->forceFill([
            'deposit_amount' => '0.00',
            'monthly_rent' => '500000.00',
        ]);
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($property, $customer));

        $property->forceFill([
            'deposit_amount' => '100000000.00',
            'monthly_rent' => '0.00',
        ]);
        $customer->forceFill([
            'rental_deposit_min' => '0.00',
            'rental_deposit_max' => '0.00',
            'rental_rent_min' => '0.00',
            'rental_rent_max' => '750000.00',
        ]);
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($property, $customer));
    }

    public function test_industrial_matching_uses_both_customer_ranges(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent, [
            'property_type' => PropertyType::Industrial,
            'land_area' => '1000',
            'building_area' => '500',
        ]);
        $customer = $this->customer($agency, $manager, $agent, [
            'desired_property_type' => PropertyType::Industrial->value,
            'min_area_sqm' => null,
            'max_area_sqm' => null,
            'land_area_min' => '900.00',
            'land_area_max' => '1100.00',
            'building_area_min' => '400.00',
            'building_area_max' => '600.00',
        ]);

        $candidate = app(PropertyCustomerMatchingService::class)->calculate($property, $customer);
        self::assertNotNull($candidate);
        self::assertSame(12.5, $candidate->areaScore);

        $property->building_area = null;
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($property, $customer));
    }

    public function test_property_top_twenty_does_not_discard_customer_side_matches(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        $customers = [];
        for ($index = 0; $index < 25; $index++) {
            $customers[] = $this->customer($agency, $manager, $agent, [
                'created_at' => now()->subMinutes(25 - $index),
            ]);
        }

        $service = app(PropertyCustomerMatchingService::class);
        $service->rebuildForAgency((int) $agency->getKey());

        self::assertSame(25, PropertyCustomerMatch::query()->count());
        self::assertSame(25, MatchNotification::query()->count());
        self::assertSame(5, PropertyCustomerMatch::query()->whereNull('property_rank')->count());
        self::assertSame(range(1, 20), PropertyCustomerMatch::query()
            ->where('property_id', $property->getKey())
            ->whereNotNull('property_rank')
            ->orderBy('property_rank')->pluck('property_rank')->all());
        self::assertSame(array_reverse(array_map(
            static fn (Customer $customer): int => (int) $customer->getKey(),
            array_slice($customers, 5),
        )), PropertyCustomerMatch::query()->whereNotNull('property_rank')
            ->orderBy('property_rank')->pluck('customer_id')->all());
        self::assertDatabaseHas('property_customer_matches', [
            'property_id' => $property->getKey(),
            'customer_id' => $customers[0]->getKey(),
            'property_rank' => null,
            'customer_rank' => 1,
        ]);
        $matchIds = PropertyCustomerMatch::query()->orderBy('id')->pluck('id')->all();
        $notificationIds = MatchNotification::query()->orderBy('id')->pluck('id')->all();

        $service->rebuildForAgency((int) $agency->getKey());

        self::assertSame($matchIds, PropertyCustomerMatch::query()->orderBy('id')->pluck('id')->all());
        self::assertSame($notificationIds, MatchNotification::query()->orderBy('id')->pluck('id')->all());
        self::assertSame(0, MatchNotification::query()->where('version', '!=', 1)->count());
    }

    public function test_customer_top_twenty_does_not_discard_property_side_matches(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $customer = $this->customer($agency, $manager, $agent);
        $properties = [];
        for ($index = 0; $index < 25; $index++) {
            $properties[] = $this->property($agency, $manager, $agent, [
                'created_at' => now()->subMinutes(25 - $index),
            ]);
        }

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        self::assertSame(25, PropertyCustomerMatch::query()->count());
        self::assertSame(25, MatchNotification::query()->count());
        self::assertSame(5, PropertyCustomerMatch::query()->whereNull('customer_rank')->count());
        self::assertSame(range(1, 20), PropertyCustomerMatch::query()
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('customer_rank')
            ->orderBy('customer_rank')->pluck('customer_rank')->all());
        self::assertSame(array_reverse(array_map(
            static fn (Property $property): int => (int) $property->getKey(),
            array_slice($properties, 5),
        )), PropertyCustomerMatch::query()->whereNotNull('customer_rank')
            ->orderBy('customer_rank')->pluck('property_id')->all());
        self::assertDatabaseHas('property_customer_matches', [
            'property_id' => $properties[0]->getKey(),
            'customer_id' => $customer->getKey(),
            'property_rank' => 1,
            'customer_rank' => null,
        ]);
    }

    public function test_union_excludes_only_pairs_outside_both_independent_top_twenty_lists(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $properties = [];
        $customers = [];
        for ($index = 0; $index < 21; $index++) {
            $createdAt = now()->subMinutes(21 - $index);
            $properties[] = $this->property($agency, $manager, $agent, ['created_at' => $createdAt]);
            $customers[] = $this->customer($agency, $manager, $agent, ['created_at' => $createdAt]);
        }

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        self::assertSame(440, PropertyCustomerMatch::query()->count());
        self::assertSame(440, MatchNotification::query()->count());
        self::assertSame(400, PropertyCustomerMatch::query()
            ->whereNotNull('property_rank')->whereNotNull('customer_rank')->count());
        self::assertSame(20, PropertyCustomerMatch::query()->whereNull('property_rank')->count());
        self::assertSame(20, PropertyCustomerMatch::query()->whereNull('customer_rank')->count());
        self::assertDatabaseMissing('property_customer_matches', [
            'property_id' => $properties[0]->getKey(),
            'customer_id' => $customers[0]->getKey(),
        ]);
        foreach ($properties as $property) {
            self::assertSame(20, PropertyCustomerMatch::query()->where('property_id', $property->getKey())
                ->whereNotNull('property_rank')->count());
        }
        foreach ($customers as $customer) {
            self::assertSame(20, PropertyCustomerMatch::query()->where('customer_id', $customer->getKey())
                ->whereNotNull('customer_rank')->count());
        }
    }

    public function test_three_valid_options_have_contiguous_ranks_and_one_notification_per_pair(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $this->property($agency, $manager, $agent);
        for ($index = 0; $index < 3; $index++) {
            $this->customer($agency, $manager, $agent);
        }

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        self::assertSame(3, PropertyCustomerMatch::query()->count());
        self::assertSame(3, MatchNotification::query()->count());
        self::assertSame([1, 2, 3], PropertyCustomerMatch::query()
            ->orderBy('property_rank')->pluck('property_rank')->all());
        self::assertSame([1, 1, 1], PropertyCustomerMatch::query()
            ->orderBy('property_rank')->pluck('customer_rank')->all());
    }

    public function test_rank_only_promotion_preserves_pair_notification_version_and_each_users_reads(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $this->property($agency, $manager, $agent);
        $customers = [];
        for ($index = 0; $index < 21; $index++) {
            $customers[] = $this->customer($agency, $manager, $agent, [
                'created_at' => now()->subMinutes(21 - $index),
            ]);
        }
        $service = app(PropertyCustomerMatchingService::class);
        $service->rebuildForAgency((int) $agency->getKey());
        $match = PropertyCustomerMatch::query()->where('customer_id', $customers[0]->getKey())->sole();
        self::assertNull($match->property_rank);
        $notification = MatchNotification::query()->where('property_customer_match_id', $match->getKey())->sole();
        $readService = app(MarkMatchNotificationReadService::class);
        $managerRead = $readService->execute($manager, $notification);
        self::assertSame(0, MatchNotificationRead::query()->where('user_id', $agent->getKey())->count());
        $agentRead = $readService->execute($agent, $notification);

        $customers[20]->forceFill(['status' => CustomerStatus::Withdrawn])->save();
        $service->rebuildForAgency((int) $agency->getKey());

        self::assertSame(20, $match->refresh()->property_rank);
        self::assertSame(1, $match->customer_rank);
        self::assertSame(1, (int) $notification->refresh()->version);
        self::assertSame($match->getKey(), (int) $notification->property_customer_match_id);
        self::assertSame([$managerRead->getKey(), $agentRead->getKey()], MatchNotificationRead::query()
            ->where('match_notification_id', $notification->getKey())->orderBy('id')->pluck('id')->all());
        self::assertSame(20, MatchNotification::query()->count());
    }

    public function test_invalid_industrial_ranges_are_rejected(): void
    {
        $this->expectException(DomainConflictException::class);

        app(CustomerInvariantValidator::class)->validate([
            'desired_property_type' => PropertyType::Industrial->value,
            'land_area_min' => '1000',
            'land_area_max' => '900',
        ]);
    }

    public function test_create_and_status_changes_dispatch_rebuild_jobs_and_activate_records(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        Bus::fake();

        $property = app(CreatePropertyService::class)->execute($manager, new CreatePropertyData(
            title: 'ملک آزمایشی',
            description: null,
            propertyType: PropertyType::Apartment,
            transactionType: TransactionType::Sale,
            assignedAgentId: (int) $agent->getKey(),
            salePrice: '5000000.00',
            depositAmount: null,
            monthlyRent: null,
            areaSqm: '100.00',
            bedrooms: 2,
            bathrooms: 1,
            floorNumber: null,
            totalFloors: null,
            yearBuilt: null,
            parkingSpaces: 1,
            hasStorageRoom: false,
            hasElevator: false,
            hasBalcony: false,
            city: 'Tehran',
            district: null,
            streetAddress: 'نشانی آزمایشی',
            postalCode: null,
            latitude: null,
            longitude: null,
            availableFrom: null,
            owners: [new PropertyOwnershipData((int) $owner->getKey(), '100.00', true)],
        ));

        self::assertNotNull($property->matching_eligible_at);
        Bus::assertDispatched(RebuildAgencyPropertyCustomerMatches::class, function (RebuildAgencyPropertyCustomerMatches $job) use ($agency): bool {
            return $job->agencyId === (int) $agency->getKey();
        });

        app(ChangePropertyStatusService::class)->execute($manager, $property, new ChangePropertyStatusData(
            PropertyStatus::Reserved,
            null,
            null,
            $this->updatedAt($property),
        ));

        self::assertSame(2, Bus::dispatched(RebuildAgencyPropertyCustomerMatches::class)->count());
    }

    public function test_property_and_customer_edits_and_customer_status_changes_dispatch_rebuild_jobs(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        Bus::fake();

        $property = app(CreatePropertyService::class)->execute($manager, new CreatePropertyData(
            title: 'ملک ویرایش‌پذیر',
            description: null,
            propertyType: PropertyType::Apartment,
            transactionType: TransactionType::Sale,
            assignedAgentId: (int) $agent->getKey(),
            salePrice: '5000000.00',
            depositAmount: null,
            monthlyRent: null,
            areaSqm: '100.00',
            bedrooms: 2,
            bathrooms: 1,
            floorNumber: null,
            totalFloors: null,
            yearBuilt: null,
            parkingSpaces: 1,
            hasStorageRoom: false,
            hasElevator: false,
            hasBalcony: false,
            city: 'Tehran',
            district: null,
            streetAddress: 'نشانی آزمایشی',
            postalCode: null,
            latitude: null,
            longitude: null,
            availableFrom: null,
            owners: [new PropertyOwnershipData((int) $owner->getKey(), '100.00', true)],
        ));
        app(UpdatePropertyService::class)->execute($manager, $property, new UpdatePropertyData(
            ['title' => 'ملک ویرایش‌شده'],
            $this->updatedAt($property),
        ));

        $customer = app(CreateCustomerService::class)->execute($manager, new CreateCustomerData(
            assignedAgentId: (int) $agent->getKey(),
            firstName: 'سارا',
            lastName: 'کریمی',
            mobile: '+989121111111',
            phone: null,
            email: 'sara@example.test',
            preferredContactMethod: PreferredContactMethod::Phone,
            intent: CustomerIntent::Buy,
            preferredPropertyTypes: [PropertyType::Apartment->value],
            budgetMin: null,
            budgetMax: null,
            desiredCity: null,
            desiredDistrict: null,
            minAreaSqm: null,
            maxAreaSqm: null,
            minBedrooms: null,
        ));
        $customer = app(UpdateCustomerService::class)->execute($manager, $customer, new UpdateCustomerData(
            ['description' => 'ویرایش مشتری'],
            $this->updatedAt($customer),
        ));
        app(UpdateCustomerService::class)->execute($manager, $customer, new UpdateCustomerData(
            ['status' => CustomerStatus::Withdrawn],
            $this->updatedAt($customer),
        ));

        self::assertSame(5, Bus::dispatched(RebuildAgencyPropertyCustomerMatches::class)->count());
        self::assertNotNull($property->refresh()->matching_eligible_at);
        self::assertNotNull($customer->refresh()->matching_eligible_at);
    }

    /** @param array<string, mixed> $overrides */
    private function property(Agency $agency, User $manager, User $agent, array $overrides = []): Property
    {
        return Property::factory()
            ->forAgency($agency, $manager, $agent)
            ->state(array_merge([
                'title' => 'آپارتمان آزمایشی',
                'property_type' => PropertyType::Apartment,
                'transaction_type' => TransactionType::Sale,
                'status' => PropertyStatus::Available,
                'sale_price' => '5000000.00',
                'deposit_amount' => null,
                'monthly_rent' => null,
                'area_sqm' => '100.00',
                'bedrooms' => 2,
                'parking_spaces' => 1,
                'city' => 'تهران',
                'district' => 'منطقه ۱',
                'matching_eligible_at' => now(),
            ], $overrides))
            ->create();
    }

    /** @param array<string, mixed> $overrides */
    private function customer(Agency $agency, User $manager, User $agent, array $overrides = []): Customer
    {
        return Customer::factory()
            ->forAgency($agency, $manager, $agent)
            ->state(array_merge([
                'first_name' => 'سارا',
                'last_name' => 'کریمی',
                'intent' => CustomerIntent::Buy,
                'status' => CustomerStatus::Active,
                'desired_property_type' => PropertyType::Apartment->value,
                'budget_min' => '4000000.00',
                'budget_max' => '6000000.00',
                'desired_city' => 'تهران',
                'desired_district' => 'منطقه ۱',
                'min_area_sqm' => '90.00',
                'max_area_sqm' => '110.00',
                'min_bedrooms' => 1,
                'matching_eligible_at' => now(),
            ], $overrides))
            ->create();
    }
}
