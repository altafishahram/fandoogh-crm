<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Tenancy\TenantContext;
use App\Models\SavedFilter;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class PhaseThreeCapabilitiesTest extends DomainTestCase
{
    public function test_agent_can_manage_personal_saved_filters_and_defaults_are_unique(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $first = $this->postJson('/api/v1/saved-filters', [
            'module' => 'properties', 'name' => 'Available Tehran',
            'filters' => ['status' => 'available', 'city' => 'Tehran'],
            'sort' => '-updated_at', 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true)->json('data');

        $second = $this->postJson('/api/v1/saved-filters', [
            'module' => 'properties', 'name' => 'Large homes',
            'filters' => ['min_area' => 150], 'sort' => 'area_sqm', 'is_default' => true,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('saved_filters', ['id' => $first['id'], 'is_default' => false]);
        $this->assertDatabaseHas('saved_filters', ['id' => $second['id'], 'is_default' => true]);
        $this->getJson('/api/v1/saved-filters')->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson('/api/v1/saved-filters/'.$first['id'])->assertNoContent();
        $this->assertDatabaseMissing('saved_filters', ['id' => $first['id']]);
    }

    public function test_saved_filter_allowlist_search_dashboard_and_report_are_exposed(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/saved-filters', [
            'module' => 'properties', 'name' => 'Unsafe', 'filters' => ['sql' => 'DROP TABLE properties'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_FILTER');

        $this->getJson('/api/v1/search?q=a')->assertUnprocessable();
        $this->getJson('/api/v1/search?q=Tehran')->assertOk()
            ->assertJsonStructure(['data' => ['properties', 'owners', 'customers']]);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure(['data' => ['properties_by_status', 'active_customers']]);
        $this->getJson('/api/v1/reports/me?from=2026-01-01&to=2026-01-31')->assertOk()
            ->assertJsonPath('data.period.from', '2025-12-31T20:30:00.000000Z');
    }

    public function test_user_cannot_update_another_users_saved_filter(): void
    {
        ['agency' => $agency, 'agent' => $agent] = $this->tenant();
        $other = User::factory()->agent($agency)->create();
        $this->establish($other);
        $filter = SavedFilter::query()->create([
            'user_id' => $other->getKey(), 'module' => 'customers', 'name' => 'Mine',
            'filters' => [], 'sort' => null, 'is_default' => false,
        ]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($agent, ['mobile']);

        $this->patchJson('/api/v1/saved-filters/'.$filter->getKey(), [
            'module' => 'customers', 'name' => 'Stolen', 'filters' => ['status' => 'active'],
        ])->assertForbidden();
    }
}
