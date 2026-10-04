<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Application\Matching\Services\MarkMatchNotificationReadService;
use App\Application\Matching\Services\RelatedMatchQuery;
use App\Domain\Tenancy\TenantContext;
use App\Filament\Agency\Resources\Customers\CustomerResource;
use App\Filament\Agency\Resources\Properties\PropertyResource;
use App\Filament\Agency\Widgets\AgencyDetails;
use App\Filament\Agent\Widgets\AgentDetails;
use App\Livewire\RelatedMatchList;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\DomainTestCase;

final class RelatedMatchPanelTest extends DomainTestCase
{
    public function test_property_list_loads_ten_then_twenty_and_never_reads_notifications(): void
    {
        [$manager, $agent, $property, $notifications] = $this->rankedMatches('property', 21);
        Filament::setCurrentPanel(Filament::getPanel('agency'));

        $this->actingAs($manager);
        $list = Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertSee('مشتریان منطبق')
            ->assertSee('۲۰ تطبیق · ۲۰ جدید')
            ->assertSee('مشتری رتبه 10 پایان')
            ->assertDontSee('مشتری رتبه 11 پایان')
            ->assertSee(RelatedMatchQuery::shortReason($notifications[0]->match))
            ->assertDontSee('دلیل کوتاه 1 پایان')
            ->assertSee('شیوه مالی: مستقیم')
            ->assertSee('امتیاز ۹۹٫۰۰ از ۱۰۰');

        app(TenantContext::class)->clear();
        $list->call('loadMore')->assertSet('visibleCount', 20)
            ->assertSee('مشتری رتبه 20 پایان')
            ->assertDontSee('مشتری رتبه 21 پایان')
            ->assertDontSee('نمایش ۱۰ تطبیق بعدی');
        $list->call('loadMore')->assertSet('visibleCount', 20);
        $this->assertDatabaseCount('match_notification_reads', 0);

        app(MarkMatchNotificationReadService::class)->execute($manager, $notifications[0]);
        $list->call('$refresh')->assertSee('۲۰ تطبیق · ۱۹ جدید');
        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->actingAs($agent);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertSee('۲۰ تطبیق · ۲۰ جدید');
        $this->assertDatabaseCount('match_notification_reads', 1);
    }

    public function test_customer_list_uses_its_own_rank_and_caps_at_twenty(): void
    {
        [$manager, $agent, $customer] = $this->rankedMatches('customer', 21);
        Filament::setCurrentPanel(Filament::getPanel('agent'));

        $this->actingAs($agent);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'customer', 'recordId' => $customer->getKey(),
        ])->assertSee('املاک منطبق')
            ->assertSee('۲۰ تطبیق · ۲۰ جدید')
            ->assertDontSee('ملک رتبه 11 پایان')
            ->call('loadMore')->assertSee('ملک رتبه 20 پایان')
            ->assertDontSee('ملک رتبه 21 پایان');
        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_cards_and_details_in_both_role_panels_show_scoped_counts(): void
    {
        [$manager, $agent, $property] = $this->rankedMatches('property', 1);
        $customer = Customer::query()->firstOrFail();

        foreach ([[$manager, 'agency'], [$agent, 'agent']] as [$actor, $panel]) {
            $this->actingAs($actor)->get('/'.$panel.'/properties')->assertOk()->assertSee('۱ تطبیق · ۱ جدید');
            $this->get('/'.$panel.'/customers')->assertOk()->assertSee('۱ تطبیق · ۱ جدید');
            $this->get('/'.$panel.'/properties/'.$property->getKey())->assertOk()->assertSee('مشتری رتبه 1 پایان');
            $this->get('/'.$panel.'/customers/'.$customer->getKey())->assertOk()->assertSee('امتیاز ۹۹٫۰۰');
        }

        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_resource_summaries_are_selected_without_per_row_count_queries(): void
    {
        [$manager] = $this->rankedMatches('customer', 20);
        $this->actingAs($manager);
        $this->establish($manager);
        Filament::setCurrentPanel(Filament::getPanel('agency'));

        DB::enableQueryLog();
        DB::flushQueryLog();
        $properties = PropertyResource::getEloquentQuery()->get();
        self::assertCount(20, $properties);
        foreach ($properties as $property) {
            self::assertSame(1, (int) $property->getAttribute('match_count'));
            self::assertSame(1, (int) $property->getAttribute('unread_match_count'));
        }
        self::assertLessThan(6, count(DB::getQueryLog()));

        DB::flushQueryLog();
        $customer = CustomerResource::getEloquentQuery()->firstOrFail();
        self::assertSame(20, (int) $customer->getAttribute('match_count'));
        self::assertSame(20, (int) $customer->getAttribute('unread_match_count'));
        self::assertLessThan(4, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_recent_dashboard_cards_link_to_matches_with_fresh_per_user_counts(): void
    {
        [$manager, $agent, $property, $notifications] = $this->rankedMatches('property', 1);

        Filament::setCurrentPanel(Filament::getPanel('agency'));
        $this->actingAs($manager);
        $dashboard = Livewire::test(AgencyDetails::class)
            ->assertSee('۱ تطبیق · ۱ جدید')
            ->assertSee('/properties/'.$property->getKey().'#related-matches', false);
        app(MarkMatchNotificationReadService::class)->execute($manager, $notifications[0]);
        $dashboard->call('refreshMatches')->assertSee('۱ تطبیق · ۰ جدید');
        $this->establish($agent);
        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->actingAs($agent);
        Livewire::test(AgentDetails::class)->assertSee('۱ تطبیق · ۱ جدید');
        $this->assertDatabaseCount('match_notification_reads', 1);
    }

    public function test_related_list_rejects_cross_agency_records_and_wrong_role_panel(): void
    {
        [$manager, $agent, $property] = $this->rankedMatches('property', 1);
        app(TenantContext::class)->clear();
        ['manager' => $otherManager] = $this->tenant();
        $this->establish($otherManager);
        Filament::setCurrentPanel(Filament::getPanel('agency'));

        $this->actingAs($otherManager);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertNotFound();

        $this->establish($agent);
        $this->actingAs($agent);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertForbidden();
        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_related_list_rechecks_activity_and_agency_activity_on_load_more(): void
    {
        [$manager, $agent, $property] = $this->rankedMatches('property', 1);
        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->actingAs($agent);
        $list = Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ]);
        $agent->is_active = false;
        $agent->save();
        $list->call('loadMore')->assertForbidden();

        Filament::setCurrentPanel(Filament::getPanel('agency'));
        $agency = $manager->agency;
        self::assertNotNull($agency);
        $agency->is_active = false;
        $agency->save();
        $this->actingAs($manager);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertForbidden();
    }

    public function test_opening_detail_is_read_only_and_exit_acknowledges_only_the_current_user(): void
    {
        [$manager, $agent, $property, $notifications] = $this->rankedMatches('property', 1);
        $notification = $notifications[0];
        $path = '/agency/match-notifications/'.$notification->getKey();
        $this->actingAs($manager)->get($path)->assertOk()
            ->assertSee('pagehide')->assertSee('livewire:navigating')->assertSee('read-on-exit');
        $this->assertDatabaseCount('match_notification_reads', 0);

        $token = csrf_token();
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->withSession(['_token' => $token]);
        $this->post($path.'/read-on-exit', ['_token' => $token, 'version' => 1])->assertNoContent();
        $this->withSession(['_token' => $token]);
        $this->post($path.'/read-on-exit', ['_token' => $token, 'version' => 1])->assertNoContent();
        $this->assertDatabaseCount('match_notification_reads', 1);
        $this->assertDatabaseHas('match_notification_reads', [
            'user_id' => $manager->getKey(), 'match_notification_id' => $notification->getKey(), 'notification_version' => 1,
        ]);
        $this->assertDatabaseMissing('match_notification_reads', ['user_id' => $agent->getKey()]);
    }

    public function test_exit_does_not_acknowledge_a_newer_unseen_version_and_rejects_future_versions(): void
    {
        [$manager, $agent, $property, $notifications] = $this->rankedMatches('property', 1);
        $notification = $notifications[0];
        $notification->update(['version' => 2]);
        $path = '/agency/match-notifications/'.$notification->getKey().'/read-on-exit';

        $token = csrf_token();
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->withSession(['_token' => $token]);
        $this->actingAs($manager)->post($path, ['_token' => $token, 'version' => 3])->assertSessionHasErrors('version');
        $this->assertDatabaseCount('match_notification_reads', 0);
        $this->withSession(['_token' => $token]);
        $this->post($path, ['_token' => $token, 'version' => 1])->assertNoContent();
        $this->assertDatabaseHas('match_notification_reads', ['user_id' => $manager->getKey(), 'notification_version' => 1]);
        $this->get('/agency/properties')->assertOk()->assertSee('۱ تطبیق · ۱ جدید');
        $this->withSession(['_token' => $token]);
        $this->post($path, ['_token' => $token, 'version' => 2])->assertNoContent();
        $this->get('/agency/properties')->assertOk()->assertSee('۱ تطبیق · ۰ جدید');
    }

    public function test_exit_endpoint_rejects_other_agencies_and_other_role_panels(): void
    {
        [$manager, $agent, $property, $notifications] = $this->rankedMatches('property', 1);
        $path = '/agency/match-notifications/'.$notifications[0]->getKey().'/read-on-exit';
        app(TenantContext::class)->clear();
        ['manager' => $otherManager] = $this->tenant();
        $token = csrf_token();
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->withSession(['_token' => $token]);
        $this->actingAs($otherManager)->post($path, ['_token' => $token, 'version' => 1])->assertNotFound();
        $this->withSession(['_token' => $token]);
        $this->actingAs($agent)->post($path, ['_token' => $token, 'version' => 1])->assertForbidden();
        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_old_records_have_no_related_matches_and_an_empty_state(): void
    {
        [$manager, $agent, $property] = $this->rankedMatches('property', 1);
        $property->update(['matching_eligible_at' => null]);
        Filament::setCurrentPanel(Filament::getPanel('agency'));

        $this->actingAs($manager);
        Livewire::test(RelatedMatchList::class, [
            'side' => 'property', 'recordId' => $property->getKey(),
        ])->assertSee('۰ تطبیق · ۰ جدید')->assertSee('هنوز تطبیق معتبری')
            ->assertDontSee('مشتری رتبه 1 پایان');
    }

    /** @return array{User, User, Property|Customer, list<MatchNotification>} */
    private function rankedMatches(string $side, int $count): array
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $propertyData = [
            'matching_eligible_at' => now(), 'sale_price' => '5000000.00', 'area_sqm' => '100.00', 'district' => 'مرزداران',
        ];
        $customerData = [
            'matching_eligible_at' => now(), 'desired_property_type' => 'apartment', 'desired_city' => 'Tehran',
            'desired_district' => 'مرزداران', 'budget_min' => '4000000.00', 'budget_max' => '6000000.00',
            'min_area_sqm' => '90.00', 'max_area_sqm' => '110.00',
        ];
        $record = $side === 'property'
            ? Property::factory()->forAgency($agency, $manager, $agent)->create($propertyData)
            : Customer::factory()->forAgency($agency, $manager, $agent)->create($customerData);
        $notifications = [];

        for ($rank = 1; $rank <= $count; $rank++) {
            $property = $record instanceof Property ? $record : Property::factory()->forAgency($agency, $manager, $agent)->create([...$propertyData, 'title' => 'ملک رتبه '.$rank.' پایان']);
            $customer = $record instanceof Customer ? $record : Customer::factory()->forAgency($agency, $manager, $agent)->create([...$customerData, 'full_name' => 'مشتری رتبه '.$rank.' پایان']);
            $match = PropertyCustomerMatch::query()->create([
                'property_id' => $property->getKey(), 'customer_id' => $customer->getKey(),
                'score' => 100 - $rank, 'financial_score' => 50, 'area_score' => 20, 'feature_score' => 10,
                'match_mode' => 'direct',
                'property_rank' => $side === 'property' ? ($rank <= 20 ? $rank : null) : 1,
                'customer_rank' => $side === 'customer' ? ($rank <= 20 ? $rank : null) : 1,
                'fingerprint' => hash('sha256', $side.$rank),
            ]);
            $notifications[] = MatchNotification::query()->create([
                'property_customer_match_id' => $match->getKey(), 'version' => 1,
                'title' => 'تطبیق '.$rank, 'body' => 'دلیل کوتاه '.$rank.' پایان',
            ]);
        }

        return [$manager, $agent, $record, $notifications];
    }
}
