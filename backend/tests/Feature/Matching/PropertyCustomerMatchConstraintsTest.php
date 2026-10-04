<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Application\Matching\Services\PropertyCustomerMatchingService;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Property\Enums\PropertyType;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\DomainTestCase;

final class PropertyCustomerMatchConstraintsTest extends DomainTestCase
{
    public function test_display_rank_indexes_use_the_selected_side_rank_after_agency_and_record_id(): void
    {
        $this->assertDisplayRankIndexes();
    }

    public function test_twentieth_rank_and_a_null_opposite_rank_are_valid_on_either_side(): void
    {
        $match = $this->match();

        DB::table('property_customer_matches')->where('id', $match->getKey())->update([
            'property_rank' => 20,
            'customer_rank' => null,
        ]);
        self::assertSame(20, $match->refresh()->property_rank);
        self::assertNull($match->customer_rank);

        DB::table('property_customer_matches')->where('id', $match->getKey())->update([
            'property_rank' => null,
            'customer_rank' => 20,
        ]);
        self::assertNull($match->refresh()->property_rank);
        self::assertSame(20, $match->customer_rank);
        self::assertSame(1, MatchNotification::query()->count());
    }

    /** @param array<string, int|null> $attributes */
    #[DataProvider('invalidAttributes')]
    public function test_rank_cutoffs_and_existing_score_limits_remain_enforced(array $attributes): void
    {
        $match = $this->match();
        $this->expectException(QueryException::class);

        DB::table('property_customer_matches')->where('id', $match->getKey())->update($attributes);
    }

    /** @return array<string, array{array<string, int|null>}> */
    public static function invalidAttributes(): array
    {
        return [
            'neither side selected' => [['property_rank' => null, 'customer_rank' => null]],
            'property rank zero' => [['property_rank' => 0]],
            'customer rank zero' => [['customer_rank' => 0]],
            'property rank beyond twenty' => [['property_rank' => 21]],
            'customer rank beyond twenty' => [['customer_rank' => 21]],
            'total score below zero' => [['score' => -1]],
            'total score above one hundred' => [['score' => 101]],
            'financial score above fifty five' => [['financial_score' => 56]],
            'area score above twenty five' => [['area_score' => 26]],
            'feature score above twenty' => [['feature_score' => 21]],
        ];
    }

    public function test_pair_uniqueness_remains_enforced(): void
    {
        $attributes = $this->match()->getAttributes();
        unset($attributes['id']);
        $this->expectException(QueryException::class);

        DB::table('property_customer_matches')->insert($attributes);
    }

    public function test_notification_uniqueness_remains_enforced(): void
    {
        $this->match();
        $attributes = MatchNotification::query()->sole()->getAttributes();
        unset($attributes['id']);
        $this->expectException(QueryException::class);

        DB::table('match_notifications')->insert($attributes);
    }

    /** @param array<string, int|null> $ranks */
    #[DataProvider('nonLegacyRanks')]
    public function test_rollback_refuses_to_discard_independent_or_extended_matches(array $ranks): void
    {
        $match = $this->match();
        $notification = MatchNotification::query()->sole();
        DB::table('property_customer_matches')->where('id', $match->getKey())->update($ranks);
        $migration = require database_path('migrations/2026_10_02_000001_expand_property_customer_match_rank_limits.php');

        try {
            $migration->down();
            self::fail('Rollback must refuse to invalidate retained matches.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('no records were deleted', $exception->getMessage());
        }

        self::assertDatabaseHas('property_customer_matches', array_merge(['id' => $match->getKey()], $ranks));
        self::assertDatabaseHas('match_notifications', ['id' => $notification->getKey(), 'version' => 1]);
        $this->assertDisplayRankIndexes();
        DB::table('property_customer_matches')->where('id', $match->getKey())->update([
            'property_rank' => 20,
            'customer_rank' => null,
        ]);
        self::assertSame(20, $match->refresh()->property_rank);
    }

    /** @return array<string, array{array<string, int|null>}> */
    public static function nonLegacyRanks(): array
    {
        return [
            'property-only pair' => [['property_rank' => 1, 'customer_rank' => null]],
            'customer-only pair' => [['property_rank' => null, 'customer_rank' => 1]],
            'property rank eleven' => [['property_rank' => 11, 'customer_rank' => 1]],
            'customer rank eleven' => [['property_rank' => 1, 'customer_rank' => 11]],
        ];
    }

    private function assertDisplayRankIndexes(): void
    {
        $indexes = array_column(Schema::getIndexes('property_customer_matches'), 'columns', 'name');

        self::assertSame(['agency_id', 'property_id', 'property_rank'], $indexes['idx_matches_property_display_rank'] ?? null);
        self::assertSame(['agency_id', 'customer_id', 'customer_rank'], $indexes['idx_matches_customer_display_rank'] ?? null);
    }

    private function match(): PropertyCustomerMatch
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        Property::factory()->forAgency($agency, $manager, $agent)->create([
            'sale_price' => '5000000.00',
            'area_sqm' => '100.00',
            'matching_eligible_at' => now(),
        ]);
        Customer::factory()->forAgency($agency, $manager, $agent)->create([
            'intent' => CustomerIntent::Buy,
            'desired_property_type' => PropertyType::Apartment->value,
            'budget_min' => '4000000.00',
            'budget_max' => '6000000.00',
            'min_area_sqm' => '90.00',
            'max_area_sqm' => '110.00',
            'matching_eligible_at' => now(),
        ]);
        app(PropertyCustomerMatchingService::class)->rebuildForAgency((int) $agency->getKey());

        return PropertyCustomerMatch::query()->sole();
    }
}
