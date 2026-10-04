<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class RelatedMatchesApiTest extends DomainTestCase
{
    public function test_lists_and_badges_use_only_this_sides_twenty_results_without_mutual_selection(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        $notifications = [];
        for ($i = 1; $i <= 25; $i++) {
            $customer = $this->customer($agency, $manager, $agent);
            $notifications[] = $this->pair($property, $customer, $i <= 20 ? $i : null, 1);
        }
        Sanctum::actingAs($agent, ['mobile']);

        $this->getJson('/api/v1/properties/'.$property->id.'/matches')
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.unread_count', 20)->assertJsonPath('data.0.match.property_rank', 1)
            ->assertJsonPath('data.0.is_read', false)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches?page=2')
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.match.property_rank', 11);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches?page=3')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 20);
        $this->getJson('/api/v1/properties/'.$property->id)
            ->assertOk()->assertJsonPath('data.match_summary.count', 20)
            ->assertJsonPath('data.match_summary.unread_count', 20);
        $this->getJson('/api/v1/properties')
            ->assertOk()->assertJsonPath('data.0.match_summary.count', 20);
        $lastMatch = $notifications[24]->match;
        self::assertNotNull($lastMatch);
        $lastCustomer = $lastMatch->customer;
        self::assertNotNull($lastCustomer);
        $this->getJson('/api/v1/customers/'.$lastCustomer->id.'/matches')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.match.property_rank', null)
            ->assertJsonPath('data.0.match.customer_rank', 1);
        $this->getJson('/api/v1/customers/'.$lastCustomer->id)
            ->assertOk()->assertJsonPath('data.match_summary.count', 1);
        self::assertSame(0, MatchNotificationRead::query()->count(), 'Listing matches must not mark them read.');
    }

    public function test_three_results_and_per_user_read_state_remain_consistent_in_all_responses(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        $notifications = [];
        for ($i = 1; $i <= 3; $i++) {
            $notifications[] = $this->pair($property, $this->customer($agency, $manager, $agent), $i, 1);
        }
        Sanctum::actingAs($agent, ['mobile']);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches')
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.unread_count', 3)->assertJsonStructure(['data' => [['short_reason', 'match' => ['match_mode_label']]]]);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.recent_properties.0.match_summary.unread_count', 3);
        $this->postJson('/api/v1/match-notifications/'.$notifications[0]->id.'/read')->assertOk();
        $this->getJson('/api/v1/properties/'.$property->id.'/matches')
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.unread_count', 2)
            ->assertJsonPath('data.0.is_read', true);
        $this->getJson('/api/v1/properties/'.$property->id)->assertOk()
            ->assertJsonPath('data.match_summary.count', 3)->assertJsonPath('data.match_summary.unread_count', 2);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.recent_properties.0.match_summary.unread_count', 2);
        $this->getJson('/api/v1/sync?resource=properties')->assertOk()
            ->assertJsonPath('data.0.match_summary.count', 3);
        $this->getJson('/api/v1/search?q=Related')->assertOk()
            ->assertJsonPath('data.properties.0.match_summary.count', 3);

        $this->establish($manager);
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches')
            ->assertOk()->assertJsonPath('meta.unread_count', 3)->assertJsonPath('data.0.is_read', false);
    }

    public function test_inactive_deleted_legacy_and_cross_tenant_records_are_not_counted_or_exposed(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $otherAgency = Agency::factory()->active()->create();
        $otherAgent = User::factory()->agent($otherAgency)->create();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        $active = $this->customer($agency, $manager, $agent);
        $inactive = $this->customer($agency, $manager, $agent);
        $legacy = $this->customer($agency, $manager, $agent);
        $deleted = $this->customer($agency, $manager, $agent);
        foreach ([$active, $inactive, $legacy, $deleted] as $i => $customer) {
            $this->pair($property, $customer, $i + 1, 1);
        }
        $inactive->forceFill(['status' => 'withdrawn'])->save();
        $legacy->forceFill(['matching_eligible_at' => null])->save();
        $deleted->delete();
        Sanctum::actingAs($agent, ['mobile']);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/properties/'.$property->id)->assertOk()
            ->assertJsonPath('data.match_summary.count', 1);
        $property->forceFill(['status' => 'reserved'])->save();
        $this->getJson('/api/v1/customers/'.$active->id.'/matches')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);

        $this->establish($otherAgent);
        Sanctum::actingAs($otherAgent, ['mobile']);
        $this->getJson('/api/v1/properties/'.$property->id.'/matches')->assertNotFound();
        $this->getJson('/api/v1/customers/'.$active->id.'/matches')->assertNotFound();
    }

    public function test_summary_is_unknown_when_not_loaded_and_loading_many_summaries_uses_one_query(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $property = $this->property($agency, $manager, $agent);
        self::assertNull(RelatedMatchQuery::summaryOf($property));
        Property::factory()->count(8)->forAgency($agency, $manager, $agent)->create(['matching_eligible_at' => now()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $records = app(RelatedMatchQuery::class)->withSummaries(Property::query(), $agent, 'property')->get();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertCount(1, $queries);
        self::assertCount(9, $records);
        foreach ($records as $record) {
            self::assertSame(['count' => 0, 'unread_count' => 0], RelatedMatchQuery::summaryOf($record));
        }
    }

    public function test_exiting_old_detail_does_not_acknowledge_a_newer_unseen_notification(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $notification = $this->pair($this->property($agency, $manager, $agent), $this->customer($agency, $manager, $agent), 1, 1);
        $notification->forceFill(['version' => 2])->save();
        Sanctum::actingAs($agent, ['mobile']);
        $this->postJson('/api/v1/match-notifications/'.$notification->id.'/read', ['notification_version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.is_read', false);
        $match = $notification->match;
        self::assertNotNull($match);
        $this->getJson('/api/v1/properties/'.$match->property_id.'/matches')
            ->assertOk()->assertJsonPath('meta.unread_count', 1);
        $this->postJson('/api/v1/match-notifications/'.$notification->id.'/read', ['notification_version' => 2])
            ->assertOk()->assertJsonPath('data.is_read', true);
    }

    private function property(Agency $agency, User $manager, User $agent): Property
    {
        return Property::factory()->forAgency($agency, $manager, $agent)->create([
            'title' => 'Related matching property', 'matching_eligible_at' => now(),
        ]);
    }

    private function customer(Agency $agency, User $manager, User $agent): Customer
    {
        return Customer::factory()->forAgency($agency, $manager, $agent)->create(['matching_eligible_at' => now()]);
    }

    private function pair(Property $property, Customer $customer, ?int $propertyRank, ?int $customerRank): MatchNotification
    {
        $match = PropertyCustomerMatch::query()->create([
            'agency_id' => $property->agency_id, 'property_id' => $property->id, 'customer_id' => $customer->id,
            'score' => 100 - ($propertyRank ?? 25), 'financial_score' => 44, 'area_score' => 20,
            'feature_score' => 16, 'match_mode' => 'direct', 'property_rank' => $propertyRank,
            'customer_rank' => $customerRank, 'fingerprint' => hash('sha256', $property->id.':'.$customer->id),
        ]);

        return MatchNotification::query()->create([
            'agency_id' => $property->agency_id, 'property_customer_match_id' => $match->id,
            'version' => 1, 'title' => 'تطبیق معتبر', 'body' => 'نوع ملک، موقعیت، متراژ و بودجه با نیاز مشتری مطابقت دارند.',
        ])->setRelation('match', $match->setRelation('customer', $customer));
    }
}
