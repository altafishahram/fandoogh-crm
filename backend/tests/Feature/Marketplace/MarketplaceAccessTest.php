<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Application\Geography\Services\LocationValidator;
use App\Application\Marketplace\Services\PublicationService;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Tenancy\AgencyScope;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\PermissionName;
use App\Models\Agency;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PublicUser;
use App\Models\User;
use Database\Seeders\IranLocationsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class MarketplaceAccessTest extends DomainTestCase
{
    public function test_fixed_code_login_normalizes_phone_without_claiming_phone_verification_and_cannot_access_crm(): void
    {
        $this->getJson('/api/v1/public/auth/config')->assertOk()->assertJsonPath('data.temporary_code', '123456');
        $login = $this->postJson('/api/v1/public/auth/login', ['phone' => '۰۹۱۲۱۲۳۴۵۶۷', 'code' => '123456'])->assertCreated()->assertJsonPath('data.user.phone_verified', false);
        $this->withToken($login->json('data.token'))->getJson('/api/v1/public/auth/me')->assertOk()->assertJsonPath('data.phone', '09121234567');
        $this->withToken($login->json('data.token'))->getJson('/api/v1/properties')->assertForbidden();
        $this->assertDatabaseHas('public_users', ['phone' => '09121234567', 'is_active' => true, 'phone_verified_at' => null]);
        PublicUser::query()->where('phone', '09121234567')->update(['is_active' => false]);
        $this->postJson('/api/v1/public/auth/login', ['phone' => '09121234567', 'code' => '123456'])->assertForbidden();
    }

    public function test_unauthenticated_listing_reads_are_denied(): void
    {
        $this->getJson('/api/v1/marketplace/listings')->assertUnauthorized();
        $this->getJson('/api/v1/marketplace/listings/1')->assertUnauthorized();
        $this->getJson('/api/v1/marketplace/listings/1/images/1/content')->assertUnauthorized();
    }

    public function test_public_and_agency_flags_are_independent_and_outputs_never_include_private_data(): void
    {
        [$agency, $manager, $property] = $this->fixture();
        $listing = app(PublicationService::class)->save($manager, $property, $this->publication(true, false));
        $public = PublicUser::query()->create(['phone' => '09121234567']);
        Sanctum::actingAs($public, ['marketplace:public']);
        $this->getJson('/api/v1/marketplace/listings?audience=public')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/marketplace/listings?audience=agency')->assertForbidden();
        Sanctum::actingAs($manager, ['mobile']);
        $detail = $this->getJson('/api/v1/marketplace/listings/'.$listing->id.'?audience=agency')->assertOk()->assertJsonPath('data.neighborhood', 'محله امن');
        foreach (['owners', 'street_address', 'plaque', 'postal_code', 'latitude', 'longitude', 'created_by_user_id', 'notes', 'history', 'match_summary', 'property_id', 'assigned_agent_id'] as $key) {
            $detail->assertJsonMissingPath('data.'.$key);
        }
        $listing->update(['share_with_agencies' => false, 'publish_public' => true]);
        $this->getJson('/api/v1/marketplace/listings?audience=agency')->assertJsonCount(0, 'data');
        Sanctum::actingAs($public, ['marketplace:public']);
        $this->getJson('/api/v1/marketplace/listings?audience=public')->assertJsonCount(1, 'data');
        $listing->update(['share_with_agencies' => true]);
        $this->getJson('/api/v1/marketplace/listings?audience=public')->assertJsonCount(1, 'data');
        $listing->update(['share_with_agencies' => false, 'publish_public' => false]);
        $this->getJson('/api/v1/marketplace/listings?audience=public')->assertJsonCount(0, 'data');
    }

    public function test_hidden_statuses_deletion_and_agency_revocation_block_details_and_new_image_access(): void
    {
        Storage::fake('local');
        [$agency, $manager, $property] = $this->fixture();
        $file = UploadedFile::fake()->image('secret-owner-filename.jpg', 20, 20);
        $photoBytes = file_get_contents($file->getRealPath());
        self::assertIsString($photoBytes);
        Storage::disk('local')->put('private/photo.jpg', $photoBytes);
        $image = PropertyImage::query()->create(['property_id' => $property->id, 'uploaded_by_user_id' => $manager->id, 'storage_path' => 'private/photo.jpg', 'original_name' => 'secret-owner-filename.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => $file->getSize(), 'width' => 20, 'height' => 20, 'is_cover' => true]);
        $listing = app(PublicationService::class)->save($manager, $property, [...$this->publication(false, true), 'image_ids' => [$image->id]]);
        Sanctum::actingAs(PublicUser::query()->create(['phone' => '09121234567']), ['marketplace:public']);
        $url = '/api/v1/marketplace/listings/'.$listing->id.'/images/'.$image->id.'/content?audience=public';
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'image/jpeg');
        foreach ([PropertyStatus::Reserved, PropertyStatus::Sold, PropertyStatus::Rented, PropertyStatus::Archived] as $status) {
            // Rented requires compatible transaction type under the existing DB constraints.
            Property::withoutGlobalScope(AgencyScope::class)->whereKey($property->id)->update(['status' => $status->value, 'transaction_type' => $status === PropertyStatus::Rented ? 'rent' : 'sale', 'sale_price' => $status === PropertyStatus::Rented ? null : '1000.00', 'deposit_amount' => $status === PropertyStatus::Rented ? '0.00' : null, 'monthly_rent' => $status === PropertyStatus::Rented ? '100.00' : null, 'closed_at' => in_array($status, [PropertyStatus::Sold, PropertyStatus::Rented], true) ? now() : null, 'archived_at' => $status === PropertyStatus::Archived ? now() : null]);
            $this->getJson('/api/v1/marketplace/listings/'.$listing->id)->assertNotFound();
            $this->get($url, ['Accept' => 'application/json'])->assertNotFound();
        }
        Property::withoutGlobalScope(AgencyScope::class)->whereKey($property->id)->update(['status' => 'available', 'closed_at' => null, 'archived_at' => null]);
        $agency->forceFill(['is_verified' => false])->save();
        $this->get($url, ['Accept' => 'application/json'])->assertNotFound();
        $agency->forceFill(['is_verified' => true, 'is_active' => false])->save();
        $this->get($url, ['Accept' => 'application/json'])->assertNotFound();
        $agency->forceFill(['is_active' => true])->save();
        Property::withoutGlobalScope(AgencyScope::class)->whereKey($property->id)->delete();
        $this->get($url, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_publish_permission_is_opt_in_for_agents_and_responder_must_be_active_same_agency(): void
    {
        [$agency, $manager, $property] = $this->fixture();
        $agent = User::factory()->agent($agency)->create();
        Sanctum::actingAs($agent, ['mobile']);
        $this->putJson('/api/v1/properties/'.$property->id.'/publication', $this->publication(false, true))->assertForbidden();
        $agent->givePermissionTo(PermissionName::PropertiesPublish->value);
        $this->putJson('/api/v1/properties/'.$property->id.'/publication', $this->publication(false, true))->assertOk();
        app(TenantContext::class)->clear();
        $other = Agency::factory()->active()->create();
        $outsider = User::factory()->agent($other)->create();
        Sanctum::actingAs($manager, ['mobile']);
        $this->putJson('/api/v1/properties/'.$property->id.'/publication', [...$this->publication(false, true), 'responding_user_id' => $outsider->id])->assertUnprocessable();
        $agency->forceFill(['is_verified' => false])->save();
        $manager->unsetRelation('agency');
        $this->putJson('/api/v1/properties/'.$property->id.'/publication', $this->publication(false, true))->assertForbidden();
    }

    public function test_complete_snapshot_relationships_and_geo_endpoint_reject_mismatched_chain(): void
    {
        $this->seed(IranLocationsSeeder::class);
        $this->assertDatabaseCount('location_provinces', 31);
        $this->assertDatabaseCount('location_counties', 484);
        $this->assertDatabaseCount('location_cities', 1481);
        $this->getJson('/api/v1/locations/provinces')->assertOk()->assertJsonCount(31, 'data');
        $this->getJson('/api/v1/locations/counties')->assertUnprocessable();
        $this->expectException(ValidationException::class);
        app(LocationValidator::class)->normalize(['province_id' => 2, 'county_id' => 999999, 'city_id' => 999999]);
    }

    public function test_publication_first_save_accepts_empty_images_but_rejects_private_copy_and_foreign_images(): void
    {
        [, $manager, $property] = $this->fixture();
        Sanctum::actingAs($manager, ['mobile']);
        $endpoint = '/api/v1/properties/'.$property->id.'/publication';
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.version', 0);
        $this->putJson($endpoint, [...$this->publication(false, true), 'expected_version' => 0])
            ->assertOk()->assertJsonPath('data.version', 1)->assertJsonCount(0, 'data.image_ids');
        $this->putJson($endpoint, [...$this->publication(false, true), 'public_description' => 'SECRET STREET'])
            ->assertUnprocessable();
        $this->putJson($endpoint, [...$this->publication(false, true), 'image_ids' => [999999]])
            ->assertUnprocessable();
        $this->putJson($endpoint, [...$this->publication(false, true), 'expected_version' => 0])
            ->assertConflict();
    }

    public function test_listing_image_discards_embedded_metadata_and_tokens_require_the_correct_ability(): void
    {
        Storage::fake('local');
        [, $manager, $property] = $this->fixture();
        $file = UploadedFile::fake()->image('private-owner-name.jpg', 20, 20);
        $bytes = file_get_contents($file->getRealPath());
        self::assertIsString($bytes);
        $metadata = "Exif\0\0PRIVATE_OWNER_GPS_35.7_51.4";
        $bytes = substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($metadata) + 2).$metadata.substr($bytes, 2);
        Storage::disk('local')->put('private/metadata.jpg', $bytes);
        $image = PropertyImage::query()->create(['property_id' => $property->id, 'uploaded_by_user_id' => $manager->id, 'storage_path' => 'private/metadata.jpg', 'original_name' => 'private-owner-name.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => strlen($bytes), 'width' => 20, 'height' => 20, 'is_cover' => true]);
        $listing = app(PublicationService::class)->save($manager, $property, [...$this->publication(false, true), 'image_ids' => [$image->id]]);
        $public = PublicUser::query()->create(['phone' => '09121234568']);
        Sanctum::actingAs($public, ['mobile']);
        $this->getJson('/api/v1/marketplace/listings')->assertForbidden();
        Sanctum::actingAs($public, ['marketplace:public']);
        $response = $this->get('/api/v1/marketplace/listings/'.$listing->id.'/images/'.$image->id.'/content')->assertOk();
        self::assertStringNotContainsString('PRIVATE_OWNER_GPS', (string) $response->getContent());
        self::assertStringNotContainsString('Exif', (string) $response->getContent());
        $this->getJson('/api/v1/marketplace/listings/'.$listing->id.'/images/999999/content')->assertNotFound();
    }

    public function test_private_phone_variants_and_postcodes_cannot_be_copied_into_public_text(): void
    {
        [$agency, $manager, $property] = $this->fixture();
        $owner = Owner::factory()->forAgency($agency, $manager)->create(['mobile' => '09121234567']);
        $property->owners()->attach($owner->id, ['agency_id' => $agency->id, 'is_primary' => true]);
        Sanctum::actingAs($manager, ['mobile']);
        foreach (['۰۹۱۲۱۲۳۴۵۶۷', '٠٩١٢١٢٣٤٥٦٧', '0912 123 4567', '+98 912 123 4567', '۱۲۳۴۵۶۷۸۹۰', '۳۵٫۷, ۵۱٫۴'] as $secret) {
            $this->putJson('/api/v1/properties/'.$property->id.'/publication', [...$this->publication(false, true), 'public_description' => 'تماس و نشانی: '.$secret])->assertUnprocessable();
        }
    }

    public function test_staff_with_required_password_change_cannot_read_marketplace_but_public_users_can(): void
    {
        [, $manager, $property] = $this->fixture();
        $listing = app(PublicationService::class)->save($manager, $property, $this->publication(true, true));
        $manager->forceFill(['must_change_password' => true])->save();
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/marketplace/listings')->assertForbidden();
        $this->getJson('/api/v1/marketplace/listings/'.$listing->id)->assertForbidden();
        $this->getJson('/api/v1/marketplace/listings/'.$listing->id.'/images/999999/content')->assertForbidden();
        Sanctum::actingAs(PublicUser::query()->create(['phone' => '09121234569']), ['marketplace:public']);
        $this->getJson('/api/v1/marketplace/listings/'.$listing->id)->assertOk();
    }

    /** @return array{Agency, User, Property} */
    private function fixture(): array
    {
        $this->seed(IranLocationsSeeder::class);
        ['agency' => $agency, 'manager' => $manager] = $this->tenant();
        $agency->forceFill(['is_verified' => true])->save();
        $manager->unsetRelation('agency');
        $this->establish($manager);
        $city = DB::table('location_cities')->first();
        self::assertNotNull($city);
        $county = DB::table('location_counties')->where('id', $city->county_id)->first();
        self::assertNotNull($county);
        $property = Property::factory()->forAgency($agency, $manager)->create(['city_id' => $city->id, 'county_id' => $county->id, 'province_id' => $county->province_id, 'district' => 'محله امن', 'street_address' => 'SECRET STREET', 'postal_code' => '1234567890', 'latitude' => '35.7000000', 'longitude' => '51.4000000']);

        return [$agency, $manager, $property];
    }

    /** @return array<string, mixed> */
    private function publication(bool $agency, bool $public): array
    {
        return ['share_with_agencies' => $agency, 'publish_public' => $public, 'public_title' => 'آگهی قابل نمایش', 'public_description' => 'توضیح بررسی‌شده', 'image_ids' => []];
    }
}
