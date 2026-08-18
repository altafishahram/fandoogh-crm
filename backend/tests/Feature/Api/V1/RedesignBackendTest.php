<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class RedesignBackendTest extends DomainTestCase
{
    public function test_embedded_owner_and_per_square_meter_price_are_stored_atomically_and_idempotently(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $operationKey = (string) Str::uuid();
        $payload = $this->salePropertyPayload('مالک نمونه');

        $first = $this->withHeader('Idempotency-Key', $operationKey)
            ->postJson('/api/v1/properties', $payload)
            ->assertCreated()
            ->assertJsonPath('data.sale_price', '10000000000.00')
            ->assertJsonPath('data.sale_price_per_sqm', '100000000')
            ->assertJsonPath('data.currency_unit', 'toman')
            ->assertJsonPath('data.created_by_role', 'agent')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.owners.0.full_name', 'مالک نمونه');

        $this->withHeader('Idempotency-Key', $operationKey)
            ->postJson('/api/v1/properties', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('owners', 1);
        $this->assertDatabaseCount('client_operations', 1);
    }

    public function test_zero_rent_and_fixed_deposit_conversion_are_supported(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/properties', [
            ...$this->basePropertyPayload('ملک اجاره‌ای', 'مالک اجاره‌دهنده'),
            'transaction_type' => 'rent',
            'deposit_amount' => '100000000',
            'monthly_rent' => '0',
            'is_convertible' => true,
            'minimum_deposit' => '50000000',
        ])->assertCreated()
            ->assertJsonPath('data.monthly_rent', '0.00')
            ->assertJsonPath('data.maximum_rent', '1500000');
    }

    public function test_shared_property_features_are_validated_stored_and_exposed(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('مالک امکانات'),
            'property_type' => 'bureau',
            'toilet_types' => ['iranian', 'western'],
            'has_master_bathroom' => true,
            'cabinet_type' => 'high_gloss',
            'heating_type' => 'floor_heating',
            'cooling_type' => 'duct_split',
            'flooring_type' => 'pvc',
            'renovation_status' => 'wallpaper',
            'building_orientation' => 'south',
            'deed_type' => 'single_page',
            'has_loan' => true,
            'is_exchangeable' => true,
            'has_pool' => true,
            'has_jacuzzi' => true,
            'has_sauna' => true,
        ])->assertCreated()
            ->assertJsonPath('data.property_type', 'bureau')
            ->assertJsonPath('data.toilet_types.0', 'iranian')
            ->assertJsonPath('data.toilet_types.1', 'western')
            ->assertJsonPath('data.has_master_bathroom', true)
            ->assertJsonPath('data.cabinet_type', 'high_gloss')
            ->assertJsonPath('data.heating_type', 'floor_heating')
            ->assertJsonPath('data.cooling_type', 'duct_split')
            ->assertJsonPath('data.flooring_type', 'pvc')
            ->assertJsonPath('data.renovation_status', 'wallpaper')
            ->assertJsonPath('data.building_orientation', 'south')
            ->assertJsonPath('data.deed_type', 'single_page')
            ->assertJsonPath('data.has_loan', true)
            ->assertJsonPath('data.is_exchangeable', true)
            ->assertJsonPath('data.has_pool', true)
            ->assertJsonPath('data.has_jacuzzi', true)
            ->assertJsonPath('data.has_sauna', true);

        $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('مالک مقدار نامعتبر'),
            'cabinet_type' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['cabinet_type']]]);
    }

    public function test_shared_customer_features_are_validated_stored_and_exposed(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'desired_property_type' => 'bureau',
            'preferred_property_types' => ['bureau'],
            'toilet_types' => ['iranian', 'western'],
            'has_master_bathroom' => true,
            'cabinet_type' => 'high_gloss',
            'heating_type' => 'floor_heating',
            'cooling_type' => 'duct_split',
            'flooring_type' => 'pvc',
            'renovation_status' => 'wallpaper',
            'building_orientation' => 'south',
            'deed_type' => 'single_page',
            'has_loan' => true,
            'is_exchangeable' => true,
            'has_pool' => true,
            'has_jacuzzi' => true,
            'has_sauna' => true,
        ])->assertCreated()
            ->assertJsonPath('data.desired_property_type', 'bureau')
            ->assertJsonPath('data.toilet_types.0', 'iranian')
            ->assertJsonPath('data.toilet_types.1', 'western')
            ->assertJsonPath('data.has_master_bathroom', true)
            ->assertJsonPath('data.cabinet_type', 'high_gloss')
            ->assertJsonPath('data.heating_type', 'floor_heating')
            ->assertJsonPath('data.cooling_type', 'duct_split')
            ->assertJsonPath('data.flooring_type', 'pvc')
            ->assertJsonPath('data.renovation_status', 'wallpaper')
            ->assertJsonPath('data.building_orientation', 'south')
            ->assertJsonPath('data.deed_type', 'single_page')
            ->assertJsonPath('data.has_loan', true)
            ->assertJsonPath('data.is_exchangeable', true)
            ->assertJsonPath('data.has_pool', true)
            ->assertJsonPath('data.has_jacuzzi', true)
            ->assertJsonPath('data.has_sauna', true);

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'heating_type' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['heating_type']]]);
    }

    public function test_villa_and_industrial_fields_are_validated_stored_and_exposed(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('Villa Owner'),
            'property_type' => 'villa',
            'building_type' => 'duplex',
        ])->assertCreated()
            ->assertJsonPath('data.property_type', 'villa')
            ->assertJsonPath('data.building_type', 'duplex');

        $this->postJson('/api/v1/properties', [
            ...$this->basePropertyPayload('Industrial Property', 'Industrial Owner'),
            'transaction_type' => 'rent',
            'property_type' => 'industrial',
            'deposit_amount' => '500000000',
            'monthly_rent' => '15000000',
            'structure_type' => 'سوله فلزی',
            'has_water' => true,
            'has_electricity' => true,
            'has_gas' => false,
            'telephone_line_count' => '4',
            'land_area' => '1200',
            'building_area' => '800',
        ])->assertCreated()
            ->assertJsonPath('data.property_type', 'industrial')
            ->assertJsonPath('data.structure_type', 'سوله فلزی')
            ->assertJsonPath('data.has_water', true)
            ->assertJsonPath('data.has_electricity', true)
            ->assertJsonPath('data.has_gas', false)
            ->assertJsonPath('data.telephone_line_count', '4')
            ->assertJsonPath('data.land_area', '1200')
            ->assertJsonPath('data.building_area', '800');

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'desired_property_type' => 'industrial',
            'preferred_property_types' => ['industrial'],
            'structure_type' => 'کارخانه بتنی',
            'has_water' => true,
            'has_electricity' => false,
            'has_gas' => true,
            'telephone_line_count' => '2',
            'land_area' => '900',
            'building_area' => '600',
        ])->assertCreated()
            ->assertJsonPath('data.desired_property_type', 'industrial')
            ->assertJsonPath('data.structure_type', 'کارخانه بتنی')
            ->assertJsonPath('data.has_water', true)
            ->assertJsonPath('data.has_electricity', false)
            ->assertJsonPath('data.has_gas', true)
            ->assertJsonPath('data.telephone_line_count', '2')
            ->assertJsonPath('data.land_area', '900')
            ->assertJsonPath('data.building_area', '600');

        $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('Invalid Villa'),
            'property_type' => 'villa',
            'building_type' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['building_type']]]);
    }

    public function test_rental_customer_building_requirements_are_stored_and_exposed(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'min_bedrooms' => 2,
            'min_parking_spaces' => 1,
            'has_elevator' => true,
            'has_balcony' => true,
        ])->assertCreated()
            ->assertJsonPath('data.min_bedrooms', 2)
            ->assertJsonPath('data.min_parking_spaces', 1)
            ->assertJsonPath('data.has_elevator', true)
            ->assertJsonPath('data.has_balcony', true);
    }

    public function test_all_agents_can_view_agency_records_and_search_property_by_owner_name(): void
    {
        ['agency' => $agency, 'agent' => $creator] = $this->tenant();
        $viewer = User::factory()->agent($agency)->create();
        Sanctum::actingAs($creator, ['mobile']);

        $propertyId = $this->postJson('/api/v1/properties', $this->salePropertyPayload('مریم رضایی'))
            ->assertCreated()->json('data.id');
        $customerId = $this->postJson('/api/v1/customers', $this->rentalCustomerPayload())
            ->assertCreated()
            ->assertJsonPath('data.assigned_agent_id', null)
            ->json('data.id');

        Sanctum::actingAs($viewer, ['mobile']);
        $this->getJson('/api/v1/properties/'.$propertyId)->assertOk();
        $this->getJson('/api/v1/properties?q='.urlencode('مریم'))
            ->assertOk()->assertJsonPath('data.0.id', $propertyId);
        $this->getJson('/api/v1/customers/'.$customerId)->assertOk();
    }

    public function test_mobile_advanced_filters_and_oldest_sort_are_supported(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $first = $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('مالک نخست'),
            'title' => 'ملک نخست',
            'district' => 'گلسار',
            'bedrooms' => 2,
        ])->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('مالک دوم'),
            'title' => 'ملک دوم',
            'district' => 'منظریه',
            'bedrooms' => 1,
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/properties?district=گلسار&area_min=90&area_max=110&bedrooms_min=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first);
        $this->getJson('/api/v1/properties?order=oldest')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first)
            ->assertJsonPath('data.1.id', $second);

        $customer = $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'desired_district' => 'گلسار',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/customers?property_type=apartment&district=گلسار&order=oldest')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $customer);
    }

    public function test_manager_can_enable_agent_status_permission_and_permission_change_revokes_tokens(): void
    {
        ['manager' => $manager, 'agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $customer = $this->postJson('/api/v1/customers', [
            'assigned_agent_id' => $agent->getKey(),
            'first_name' => 'قدیمی',
            'last_name' => 'سازگار',
            'mobile' => '+989121111111',
            'intent' => 'buy',
        ])->assertCreated()->json('data');

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'inactive',
            'expected_version' => 1,
        ])->assertForbidden();

        $agent->createToken('old-device', ['mobile'], now()->addDays(30));
        Sanctum::actingAs($manager, ['mobile']);
        $permissions = array_map(
            static fn (PermissionName $permission): string => $permission->value,
            [...RoleName::agentDefaultPermissions(), PermissionName::CustomersChangeStatus],
        );
        $this->putJson('/api/v1/agents/'.$agent->getKey().'/permissions', [
            'permissions' => $permissions,
        ])->assertOk()
            ->assertJsonFragment(['customers.change_status']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $agent->getKey()]);

        $agent->refresh();
        Sanctum::actingAs($agent, ['mobile']);
        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'inactive',
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_version_conflict_rejects_second_property_update(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $property = $this->postJson('/api/v1/properties', $this->salePropertyPayload('مالک هم‌زمانی'))
            ->assertCreated()->json('data');

        $this->patchJson('/api/v1/properties/'.$property['id'], [
            'title' => 'ویرایش نخست',
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.lock_version', 2);

        $this->patchJson('/api/v1/properties/'.$property['id'], [
            'title' => 'ویرایش قدیمی',
            'expected_version' => 1,
        ])->assertStatus(409)->assertJsonPath('error.code', 'STALE_RECORD');
    }

    public function test_customer_history_is_exposed_and_reopening_requires_a_reason(): void
    {
        ['agent' => $agent] = $this->tenant();
        $agent->givePermissionTo(PermissionName::CustomersChangeStatus->value);
        Sanctum::actingAs($agent, ['mobile']);
        $customer = $this->postJson('/api/v1/customers', $this->rentalCustomerPayload())
            ->assertCreated()->json('data');

        $finalized = $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'finalized',
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.lock_version', 2)->json('data');

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'active',
            'expected_version' => $finalized['lock_version'],
        ])->assertStatus(409);

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'active',
            'reason' => 'درخواست مشتری دوباره فعال شد.',
            'expected_version' => $finalized['lock_version'],
        ])->assertOk()->assertJsonPath('data.lock_version', 3);

        $this->getJson('/api/v1/customers/'.$customer['id'].'/history')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.reason', 'درخواست مشتری دوباره فعال شد.');
    }

    public function test_agent_with_update_permission_can_send_unchanged_customer_status(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $customer = $this->postJson('/api/v1/customers', $this->rentalCustomerPayload())
            ->assertCreated()->json('data');

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'full_name' => 'مشتری ویرایش‌شده',
            'status' => 'active',
            'expected_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'مشتری ویرایش‌شده')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_long_customer_status_is_persisted_without_truncation(): void
    {
        ['agent' => $agent] = $this->tenant();
        $agent->givePermissionTo(PermissionName::CustomersChangeStatus->value);
        Sanctum::actingAs($agent, ['mobile']);
        $customer = $this->postJson('/api/v1/customers', $this->rentalCustomerPayload())
            ->assertCreated()->json('data');

        $this->patchJson('/api/v1/customers/'.$customer['id'], [
            'status' => 'transacted_elsewhere',
            'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'transacted_elsewhere');
    }

    public function test_incremental_sync_includes_soft_deleted_records(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $propertyId = $this->postJson('/api/v1/properties', $this->salePropertyPayload('مالک همگام‌سازی'))
            ->assertCreated()->json('data.id');

        /** @var Property $property */
        $property = Property::query()->findOrFail($propertyId);
        $property->delete();

        $this->getJson('/api/v1/sync?resource=properties')
            ->assertOk()
            ->assertJsonPath('data.0.id', $propertyId)
            ->assertJsonPath('meta.has_more', false)
            ->assertJson(fn ($json) => $json->whereType('data.0.deleted_at', 'string')->etc());
    }

    public function test_land_property_only_exposes_land_fields_and_cannot_be_rented(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/properties', [
            ...$this->salePropertyPayload('مالک زمین'),
            'property_type' => 'land_old_building',
            'deed_type' => 'single_page',
            'has_loan' => true,
            'is_exchangeable' => true,
            'can_aggregate' => true,
            'land_frontage' => '12',
        ])->assertCreated()
            ->assertJsonPath('data.property_type', 'land_old_building')
            ->assertJsonPath('data.can_aggregate', true)
            ->assertJsonPath('data.land_frontage', '12')
            ->assertJsonPath('data.has_loan', true)
            ->assertJsonPath('data.is_exchangeable', true)
            ->assertJsonPath('data.year_built', null)
            ->assertJsonPath('data.toilet_types', null);

        $this->postJson('/api/v1/properties', [
            ...$this->basePropertyPayload('زمین اجاره‌ای', 'مالک زمین اجاره‌ای'),
            'property_type' => 'land_old_building',
            'transaction_type' => 'rent',
            'deposit_amount' => '100000000',
            'monthly_rent' => '1000000',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'DOMAIN_CONFLICT');
    }

    public function test_rental_property_and_customer_do_not_accept_loan_or_exchange_fields(): void
    {
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);

        $this->postJson('/api/v1/properties', [
            ...$this->basePropertyPayload('ملک اجاره‌ای نامعتبر', 'مالک اجاره‌ای'),
            'transaction_type' => 'rent',
            'deposit_amount' => '100000000',
            'monthly_rent' => '1000000',
            'year_built' => 1400,
            'has_loan' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'DOMAIN_CONFLICT');

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'desired_property_type' => 'land_old_building',
            'preferred_property_types' => ['land_old_building'],
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'DOMAIN_CONFLICT');

        $this->postJson('/api/v1/customers', [
            ...$this->rentalCustomerPayload(),
            'has_loan' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'DOMAIN_CONFLICT');
    }

    /** @return array<string, mixed> */
    private function salePropertyPayload(string $ownerName): array
    {
        return [
            ...$this->basePropertyPayload('آپارتمان فروشی', $ownerName),
            'transaction_type' => 'sale',
            'sale_price_per_sqm' => '100000000',
            'price_input_mode' => 'per_sqm',
        ];
    }

    /** @return array<string, mixed> */
    private function basePropertyPayload(string $title, string $ownerName): array
    {
        return [
            'title' => $title,
            'property_type' => 'apartment',
            'area_sqm' => '100',
            'city' => 'تهران',
            'street_address' => 'خیابان نمونه',
            'plaque' => '۱۷',
            'delivery_status' => 'ready',
            'owner' => [
                'full_name' => $ownerName,
                'mobile' => '+989121234567',
                'phone' => '02112345678',
                'notes' => 'مالک پاسخ‌گو است.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function rentalCustomerPayload(): array
    {
        return [
            'full_name' => 'مشتری اجاره‌ای',
            'mobile' => '+989129876543',
            'intent' => 'rent',
            'desired_property_type' => 'apartment',
            'min_area_sqm' => '70',
            'max_area_sqm' => '110',
            'rental_deposit_min' => '75000000',
            'rental_deposit_max' => '100000000',
            'rental_rent_min' => '0',
            'rental_rent_max' => '750000',
            'accepts_rent_conversion' => true,
        ];
    }
}
