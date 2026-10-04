<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Application\Matching\Services\PropertyCustomerMatchingService;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Matching\Enums\MatchMode;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Support\DomainTestCase;

final class PropertyCustomerMatchingTest extends DomainTestCase
{
    public function test_direct_sale_match_creates_one_shared_agency_notification(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);

        $property = $this->saleProperty($agency, $manager, $agent);
        $customer = $this->buyCustomer($agency, $manager, $agent);

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        $match = PropertyCustomerMatch::query()->sole();
        self::assertSame($property->getKey(), $match->property_id);
        self::assertSame($customer->getKey(), $match->customer_id);
        self::assertSame(MatchMode::Direct, $match->match_mode);
        self::assertGreaterThan(0, (float) $match->score);
        self::assertSame(1, MatchNotification::query()->count());
    }

    public function test_old_records_and_cross_agency_records_are_not_candidates(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $otherAgency = Agency::factory()->active()->create();
        $this->establish($manager);

        $oldProperty = $this->saleProperty($agency, $manager, $agent, null);
        $oldProperty->forceFill(['matching_eligible_at' => null])->save();
        $oldCustomer = $this->buyCustomer($agency, $manager, $agent, null);
        $oldCustomer->forceFill(['matching_eligible_at' => null])->save();
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($oldProperty, $oldCustomer));

        $otherProperty = Property::factory()->forAgency($otherAgency, $manager, $agent)->make([
            'agency_id' => $otherAgency->getKey(),
            'matching_eligible_at' => now(),
        ]);
        $otherCustomer = Customer::factory()->forAgency($otherAgency, $manager, $agent)->make([
            'agency_id' => $otherAgency->getKey(),
            'full_name' => 'مشتری آژانس دیگر',
            'desired_property_type' => PropertyType::Apartment->value,
            'preferred_property_types' => [PropertyType::Apartment->value],
            'budget_min' => '1000000',
            'budget_max' => '10000000',
            'min_area_sqm' => '50',
            'max_area_sqm' => '150',
            'matching_eligible_at' => now(),
        ]);
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($oldProperty, $otherCustomer));
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($otherProperty, $oldCustomer));
    }

    public function test_rent_conversion_matches_only_on_the_real_conversion_path(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);

        $property = Property::factory()->forAgency($agency, $manager, $agent)->rent()->create([
            'property_type' => PropertyType::Apartment,
            'deposit_amount' => '100000000',
            'monthly_rent' => '0',
            'is_convertible' => true,
            'minimum_deposit' => '50000000',
            'matching_eligible_at' => now(),
        ]);
        $customer = Customer::factory()->forAgency($agency, $manager, $agent)->renter()->create([
            'full_name' => 'مشتری اجاره‌ای',
            'intent' => CustomerIntent::Rent,
            'desired_property_type' => PropertyType::Apartment->value,
            'preferred_property_types' => [PropertyType::Apartment->value],
            'rental_deposit_min' => '50000000',
            'rental_deposit_max' => '80000000',
            'rental_rent_min' => '600000',
            'rental_rent_max' => '1500000',
            'accepts_rent_conversion' => true,
            'min_area_sqm' => '50',
            'max_area_sqm' => '150',
            'matching_eligible_at' => now(),
        ]);

        $candidate = app(PropertyCustomerMatchingService::class)->calculate($property, $customer);

        self::assertNotNull($candidate);
        self::assertSame(MatchMode::Converted, $candidate->mode);
        self::assertSame(50000000.0, $candidate->matchedDepositMin);
        self::assertSame(80000000.0, $candidate->matchedDepositMax);
        self::assertSame(600000.0, $candidate->matchedRentMin);
        self::assertSame(1500000.0, $candidate->matchedRentMax);
    }

    public function test_invalid_financial_or_feature_requirements_reject_candidate(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);

        $property = $this->saleProperty($agency, $manager, $agent);
        $customer = $this->buyCustomer($agency, $manager, $agent);
        $customer->forceFill([
            'budget_min' => '1000000000',
            'budget_max' => '2000000000',
            'has_storage_room' => true,
        ]);
        $customer->save();

        $freshProperty = $property->fresh();
        $freshCustomer = $customer->fresh();
        self::assertInstanceOf(Property::class, $freshProperty);
        self::assertInstanceOf(Customer::class, $freshCustomer);
        self::assertNull(app(PropertyCustomerMatchingService::class)->calculate($freshProperty, $freshCustomer));
    }

    public function test_disqualified_property_removes_match_and_notification(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->saleProperty($agency, $manager, $agent);
        $this->buyCustomer($agency, $manager, $agent);
        $service = app(PropertyCustomerMatchingService::class);

        $service->rebuildForAgency((int) $agency->getKey());
        self::assertSame(1, PropertyCustomerMatch::query()->count());
        self::assertSame(1, MatchNotification::query()->count());

        $property->forceFill(['status' => PropertyStatus::Reserved])->save();
        $service->rebuildForAgency((int) $agency->getKey());

        self::assertSame(0, PropertyCustomerMatch::query()->count());
        self::assertSame(0, MatchNotification::query()->count());
    }

    public function test_top_twenty_ranks_preserve_all_twenty_five_customer_side_pairs(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);

        $property = $this->saleProperty($agency, $manager, $agent);
        for ($index = 0; $index < 25; $index++) {
            $this->buyCustomer($agency, $manager, $agent, now()->subMinutes($index));
        }

        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        self::assertSame(25, PropertyCustomerMatch::query()->count());
        self::assertSame(25, MatchNotification::query()->count());
        self::assertSame(20, PropertyCustomerMatch::query()->where('property_id', $property->getKey())
            ->whereNotNull('property_rank')->count());
        self::assertSame(5, PropertyCustomerMatch::query()->where('property_id', $property->getKey())
            ->whereNull('property_rank')->where('customer_rank', 1)->count());
    }

    private function saleProperty(Agency $agency, User $manager, User $agent, ?Carbon $eligibleAt = null): Property
    {
        return Property::factory()->forAgency($agency, $manager, $agent)->create([
            'property_type' => PropertyType::Apartment,
            'transaction_type' => TransactionType::Sale,
            'sale_price' => '900000000',
            'area_sqm' => '100',
            'parking_spaces' => 1,
            'has_storage_room' => false,
            'matching_eligible_at' => $eligibleAt ?? now(),
        ]);
    }

    private function buyCustomer(Agency $agency, User $manager, User $agent, ?Carbon $eligibleAt = null): Customer
    {
        return Customer::factory()->forAgency($agency, $manager, $agent)->create([
            'full_name' => 'مشتری خرید',
            'intent' => CustomerIntent::Buy,
            'status' => CustomerStatus::Active,
            'desired_property_type' => PropertyType::Apartment->value,
            'preferred_property_types' => [PropertyType::Apartment->value],
            'budget_min' => '800000000',
            'budget_max' => '1000000000',
            'min_area_sqm' => '80',
            'max_area_sqm' => '120',
            'matching_eligible_at' => $eligibleAt ?? now(),
        ]);
    }
}
