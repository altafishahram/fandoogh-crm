<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Application\Property\Services\CreatePropertyService;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\PermissionName;
use App\Models\Owner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class CoreDomainApiTest extends DomainTestCase
{
    public function test_agent_can_create_and_use_property_owner_and_note_endpoints(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $ownerId = $this->postJson('/api/v1/owners', $this->ownerPayload())
            ->assertCreated()
            ->assertJsonPath('data.owner_type', 'person')
            ->json('data.id');

        $propertyResponse = $this->postJson('/api/v1/properties', $this->propertyPayload((int) $ownerId));
        $propertyResponse->assertCreated()
            ->assertJsonPath('data.assigned_agent_id', $agent->getKey())
            ->assertJsonPath('data.status', 'available');
        $propertyId = (int) $propertyResponse->json('data.id');

        $this->getJson('/api/v1/properties/'.$propertyId)->assertOk()
            ->assertJsonPath('data.owners.0.id', $ownerId);
        $this->postJson('/api/v1/properties/'.$propertyId.'/notes', ['body' => 'Call owner tomorrow.'])
            ->assertCreated()->assertJsonPath('data.body', 'Call owner tomorrow.');
        $this->getJson('/api/v1/properties/'.$propertyId.'/history')->assertOk()
            ->assertJsonPath('data.0.action', 'created');
    }

    public function test_route_binding_hides_cross_tenant_property(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        $owner = Owner::factory()->forAgency($agency, $manager)->create();
        $property = app(CreatePropertyService::class)
            ->execute($manager, $this->salePropertyData($owner, $agent));

        app(TenantContext::class)->clear();
        ['agent' => $otherAgent] = $this->tenant();
        Sanctum::actingAs($otherAgent, ['mobile']);

        $this->getJson('/api/v1/properties/'.$property->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_agent_can_create_and_update_assigned_customer(): void
    {
        ['agent' => $agent] = $this->tenant();
        $agent->givePermissionTo(PermissionName::CustomersChangeStatus->value);
        Sanctum::actingAs($agent, ['mobile']);

        $response = $this->postJson('/api/v1/customers', [
            'assigned_agent_id' => $agent->getKey(),
            'first_name' => 'Nima', 'last_name' => 'Moradi', 'mobile' => '+989121234567',
            'preferred_contact_method' => 'phone', 'intent' => 'buy',
            'preferred_property_types' => ['apartment'],
        ])->assertCreated()->assertJsonPath('data.assigned_agent_id', $agent->getKey());

        $customer = $response->json('data');
        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'inactive', 'expected_updated_at' => $customer['updated_at'],
        ])->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_stale_customer_update_returns_stable_conflict_code(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $customer = $this->postJson('/api/v1/customers', [
            'assigned_agent_id' => $agent->getKey(),
            'first_name' => 'Nima', 'last_name' => 'Moradi', 'mobile' => '+989121234567',
            'preferred_contact_method' => 'phone', 'intent' => 'buy',
        ])->assertCreated()->json('data');

        DB::table('customers')->where('id', $customer['id'])->update([
            'first_name' => 'Changed elsewhere',
            'updated_at' => CarbonImmutable::parse($customer['updated_at'])->addSecond(),
        ]);

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'last_name' => 'Client change', 'expected_updated_at' => $customer['updated_at'],
        ])->assertStatus(409)->assertJsonPath('error.code', 'STALE_RECORD');
    }

    /** @return array<string, mixed> */
    private function ownerPayload(): array
    {
        return [
            'owner_type' => 'person', 'first_name' => 'Ali', 'last_name' => 'Ahmadi',
            'mobile' => '+989121234567', 'email' => 'ALI@EXAMPLE.TEST',
        ];
    }

    /** @return array<string, mixed> */
    private function propertyPayload(int $ownerId): array
    {
        return [
            'title' => 'Apartment', 'property_type' => 'apartment', 'transaction_type' => 'sale',
            'sale_price' => '2500000.00', 'city' => 'Tehran', 'street_address' => 'Test street',
            'owners' => [[
                'owner_id' => $ownerId, 'ownership_percentage' => '100.00', 'is_primary' => true,
            ]],
        ];
    }
}
