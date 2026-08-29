<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class MatchNotificationApiTest extends DomainTestCase
{
    public function test_version_one_notification_routes_are_authenticated(): void
    {
        self::assertNotNull(Route::getRoutes()->getByName('api.v1.match-notifications.index'));
        self::assertNotNull(Route::getRoutes()->getByName('api.v1.match-notifications.show'));
        self::assertNotNull(Route::getRoutes()->getByName('api.v1.match-notifications.read.store'));

        $this->getJson('/api/v1/match-notifications')->assertUnauthorized();
    }

    public function test_each_user_has_an_independent_read_state_and_cross_tenant_records_are_hidden(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $otherAgency = Agency::factory()->active()->create();
        $otherManager = User::factory()->agencyManager($otherAgency)->create();
        $otherAgent = User::factory()->agent($otherAgency)->create();

        $this->establish($manager);
        $property = Property::factory()->forAgency($agency, $manager, $agent)->create();
        $customer = Customer::factory()->forAgency($agency, $manager, $agent)->create();
        $match = PropertyCustomerMatch::query()->create([
            'agency_id' => $agency->getKey(),
            'property_id' => $property->getKey(),
            'customer_id' => $customer->getKey(),
            'score' => 80,
            'financial_score' => 44,
            'area_score' => 20,
            'feature_score' => 16,
            'match_mode' => 'direct',
            'property_rank' => 1,
            'customer_rank' => 1,
            'fingerprint' => str_repeat('a', 64),
        ]);
        $notification = MatchNotification::query()->create([
            'agency_id' => $agency->getKey(),
            'property_customer_match_id' => $match->getKey(),
            'version' => 1,
            'title' => 'تطبیق آزمایشی',
            'body' => 'یک تطبیق آزمایشی ثبت شد.',
        ]);

        Sanctum::actingAs($agent, ['mobile']);
        $this->getJson('/api/v1/match-notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->getKey())
            ->assertJsonPath('data.0.is_read', false);
        $this->postJson('/api/v1/match-notifications/'.$notification->getKey().'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->establish($manager);
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/match-notifications')
            ->assertOk()
            ->assertJsonPath('data.0.is_read', false);

        $this->establish($otherAgent);
        Sanctum::actingAs($otherAgent, ['mobile']);
        $this->getJson('/api/v1/match-notifications/'.$notification->getKey())
            ->assertNotFound();
        $this->postJson('/api/v1/match-notifications/'.$notification->getKey().'/read')
            ->assertNotFound();

        // Keep the second tenant variables exercised so an accidental fixture
        // change cannot silently remove the cross-tenant setup.
        self::assertSame($otherAgency->getKey(), $otherManager->agency_id);
    }
}
