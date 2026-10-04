<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Application\Geography\Services\LocationQuery;
use App\Application\Marketplace\Services\PublicationService;
use App\Domain\Tenancy\TenantContext;
use App\Filament\Agency\Pages\Marketplace;
use App\Filament\Agency\Resources\Customers\Pages\ListCustomers;
use App\Filament\Agency\Resources\Properties\Pages\ListProperties;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\IranLocationsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Support\DomainTestCase;

final class MarketplacePanelTest extends DomainTestCase
{
    public function test_marketplace_defaults_and_parent_changes_clear_descendants(): void
    {
        [$agency, $manager] = $this->geographicTenant();
        $page = Livewire::test(Marketplace::class)
            ->assertSet('provinceFilter', $agency->province_id)
            ->assertSet('countyFilter', $agency->county_id)
            ->assertSet('cityFilter', $agency->city_id);
        $page->set('provinceFilter', null)->assertSet('countyFilter', null)->assertSet('cityFilter', null);
        $page->set('countyFilter', $agency->county_id)->set('cityFilter', $agency->city_id)
            ->set('countyFilter', null)->assertSet('cityFilter', null);
    }

    public function test_property_and_customer_filter_forms_use_agency_defaults_and_parent_resets(): void
    {
        [$agency] = $this->geographicTenant();
        $properties = Livewire::test(ListProperties::class)
            ->assertSet('tableFilters.location.province_id', $agency->province_id)
            ->assertSet('tableFilters.location.county_id', $agency->county_id)
            ->assertSet('tableFilters.location.city_id', $agency->city_id);
        $properties->set('tableDeferredFilters.location.province_id', null)
            ->assertSet('tableDeferredFilters.location.county_id', null)
            ->assertSet('tableDeferredFilters.location.city_id', null);
        $customers = Livewire::test(ListCustomers::class)
            ->assertSet('tableFilters.location.desired_province_id', $agency->province_id)
            ->assertSet('tableFilters.location.desired_county_id', $agency->county_id)
            ->assertSet('tableFilters.location.desired_city_id', $agency->city_id);
        $customers->set('tableDeferredFilters.location.desired_province_id', null)
            ->assertSet('tableDeferredFilters.location.desired_county_id', null)
            ->assertSet('tableDeferredFilters.location.desired_city_id', null);
    }

    public function test_publication_dialog_never_prefills_private_title(): void
    {
        [$agency, $manager] = $this->geographicTenant();
        $property = Property::factory()->forAgency($agency, $manager)->create(['title' => 'PRIVATE INTERNAL TITLE', 'province_id' => $agency->province_id, 'county_id' => $agency->county_id, 'city_id' => $agency->city_id]);
        Livewire::test(ListProperties::class)
            ->mountAction(TestAction::make('publication')->table($property))->assertActionDataSet(['public_title' => '', 'public_description' => null, 'image_ids' => [], 'expected_version' => 0]);
    }

    public function test_unambiguous_legacy_city_text_remains_visible_without_assigning_location_ids(): void
    {
        [$agency, $manager] = $this->geographicTenant();
        $name = DB::table('location_cities')->where('id', $agency->city_id)->value('name');
        self::assertSame(1, DB::table('location_cities')->where('name', $name)->count());
        $legacy = Property::factory()->forAgency($agency, $manager)->create(['city' => $name]);
        $customer = Customer::factory()->forAgency($agency, $manager, $manager)->create(['desired_city' => $name]);
        Livewire::test(ListProperties::class)->assertCanSeeTableRecords([$legacy]);
        Livewire::test(ListCustomers::class)->assertCanSeeTableRecords([$customer]);
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/properties?city_id='.$agency->city_id)->assertOk()->assertJsonPath('data.0.id', $legacy->id);
        $this->getJson('/api/v1/customers?city_id='.$agency->city_id)->assertOk()->assertJsonPath('data.0.id', $customer->id);
        self::assertNull($legacy->refresh()->city_id);
        self::assertNull($customer->refresh()->desired_city_id);
        $ambiguousName = DB::table('location_cities')->select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->value('name');
        self::assertIsString($ambiguousName);
        $ambiguousId = DB::table('location_cities')->where('name', $ambiguousName)->value('id');
        $ambiguous = Property::factory()->forAgency($agency, $manager)->create(['city' => $ambiguousName]);
        self::assertFalse(app(LocationQuery::class)->apply(Property::query(), ['city_id' => $ambiguousId])->whereKey($ambiguous->id)->exists());
    }

    public function test_closing_selected_listing_clears_detail_without_disabling_browse(): void
    {
        [$publisher, $manager] = $this->geographicTenant();
        $property = Property::factory()->forAgency($publisher, $manager)->create([
            'province_id' => $publisher->province_id, 'county_id' => $publisher->county_id, 'city_id' => $publisher->city_id,
            'street_address' => 'PRIVATE ADDRESS', 'district' => 'محله قابل نمایش',
        ]);
        $listing = app(PublicationService::class)->save($manager, $property, ['share_with_agencies' => true, 'publish_public' => false, 'public_title' => 'عنوان آگهی امن', 'public_description' => null, 'image_ids' => []]);
        [$viewer, $viewerManager] = $this->geographicTenant();
        $page = Livewire::test(Marketplace::class)->call('selectListing', $listing->id)
            ->assertSee('عنوان آگهی امن')->assertSee('محله قابل نمایش')->assertDontSee('PRIVATE ADDRESS');
        $listing->update(['share_with_agencies' => false]);
        $page->call('$refresh')->assertSet('selectedListing', null)
            ->assertSet('selectedListingClosed', true)->assertSee('این آگهی دیگر در دسترس نیست.');
    }

    /** @return array{Agency, User} */
    private function geographicTenant(): array
    {
        app(TenantContext::class)->clear();
        $this->seed(IranLocationsSeeder::class);
        ['agency' => $agency, 'manager' => $manager] = $this->tenant();
        $city = DB::table('location_cities')->first();
        self::assertNotNull($city);
        $county = DB::table('location_counties')->where('id', $city->county_id)->first();
        self::assertNotNull($county);
        $agency->forceFill(['is_verified' => true, 'province_id' => $county->province_id, 'county_id' => $county->id, 'city_id' => $city->id])->save();
        $manager->unsetRelation('agency');
        $this->actingAs($manager);
        $this->establish($manager);
        Filament::setCurrentPanel(Filament::getPanel('agency'));

        return [$agency, $manager];
    }
}
