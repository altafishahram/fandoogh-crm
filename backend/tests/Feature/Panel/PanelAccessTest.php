<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Filament\Agency\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Agency\Resources\Properties\Pages\CreateProperty;
use App\Filament\Agent\Widgets\AgentDetails;
use App\Filament\Shared\Pages\Profile;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Livewire;
use Tests\Support\DomainTestCase;

final class PanelAccessTest extends DomainTestCase
{
    public function test_inactive_agency_receives_the_persian_forbidden_page(): void
    {
        $agency = Agency::factory()->create();
        $manager = User::factory()->agencyManager($agency)->create();

        $this->actingAs($manager)->get('/agency')
            ->assertForbidden()
            ->assertSee('دسترسی غیرمجاز است')
            ->assertSee('دفتر املاکی')
            ->assertDontSee('ملک بان')
            ->assertSee('فعال‌بودن حساب و آژانس را بررسی کنید')
            ->assertSee('طراحی‌شده توسط فندوق استودیو');
    }

    public function test_each_role_can_open_only_its_panel_and_operational_pages_render(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin);
        foreach ([
            '/control', '/control/agencies', '/control/agencies/create',
            '/control/agencies/'.$agency->getKey(), '/control/agencies/'.$agency->getKey().'/edit',
            '/control/users', '/control/users/create', '/control/users/'.$manager->getKey(),
            '/control/users/'.$manager->getKey().'/edit',
        ] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/agency')->assertForbidden();
        $this->get('/agent')->assertForbidden();

        $this->actingAs($manager);
        foreach ([
            '/agency', '/agency/properties', '/agency/properties/create', '/agency/owners', '/agency/owners/create',
            '/agency/customers', '/agency/customers/create', '/agency/users', '/agency/users/create',
            '/agency/users/'.$agent->getKey(), '/agency/users/'.$agent->getKey().'/edit',
            '/agency/saved-filters', '/agency/saved-filters/create', '/agency/reports', '/agency/search', '/agency/agency-settings',
        ] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/control')->assertForbidden();
        $this->get('/agent')->assertForbidden();

        $this->actingAs($agent);
        foreach ([
            '/agent', '/agent/properties', '/agent/properties/create', '/agent/customers', '/agent/customers/create',
            '/agent/saved-filters', '/agent/saved-filters/create', '/agent/reports', '/agent/search',
        ] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/control')->assertForbidden();
        $this->get('/agency')->assertForbidden();
    }

    public function test_required_password_change_redirects_to_the_current_panel_profile(): void
    {
        ['agency' => $agency] = $this->tenant();
        $manager = User::factory()->agencyManager($agency)->mustChangePassword()->create();

        $this->actingAs($manager)->get('/agency/properties')
            ->assertRedirect('/agency/profile');
        $this->actingAs($manager)->get('/agency/profile')->assertOk();
    }

    public function test_required_password_change_returns_the_user_to_the_current_panel_dashboard(): void
    {
        $superAdmin = User::factory()->superAdmin()->mustChangePassword()->create();
        $newPassword = 'Changed-password-456!';

        Filament::setCurrentPanel(Filament::getPanel('control'));

        /** @var class-string<Component> $profileComponent */
        $profileComponent = Profile::class;
        $component = Livewire::actingAs($superAdmin)
            ->test($profileComponent) // @phpstan-ignore argument.templateType
            ->set('data', [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'password' => $newPassword,
                'passwordConfirmation' => $newPassword,
                'currentPassword' => 'Test-password-123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $superAdmin->refresh();

        self::assertFalse($superAdmin->must_change_password);
        self::assertTrue(Hash::check($newPassword, $superAdmin->password));
        $component->assertRedirect(Filament::getPanel('control')->getUrl());
    }

    public function test_agent_sees_all_agency_records_but_not_other_agencies_in_redesigned_lists(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $colleague = User::factory()->agent($agency)->create();
        $otherAgency = Agency::factory()->active()->create();
        $otherManager = User::factory()->agencyManager($otherAgency)->create();
        $otherAgent = User::factory()->agent($otherAgency)->create();

        Property::factory()->forAgency($agency, $manager, $colleague)->create(['title' => 'ملک همکار قابل مشاهده']);
        Property::factory()->forAgency($otherAgency, $otherManager, $otherAgent)->create(['title' => 'ملک آژانس دیگر']);
        Customer::factory()->forAgency($agency, $manager, $colleague)->create(['full_name' => 'مشتری همکار قابل مشاهده']);
        Customer::factory()->forAgency($otherAgency, $otherManager, $otherAgent)->create(['full_name' => 'مشتری آژانس دیگر']);

        $this->actingAs($agent)->get('/agent/properties')
            ->assertOk()
            ->assertSee('ملک همکار قابل مشاهده')
            ->assertDontSee('ملک آژانس دیگر');
        $this->get('/agent/customers')
            ->assertOk()
            ->assertSee('مشتری همکار قابل مشاهده')
            ->assertDontSee('مشتری آژانس دیگر');
        $this->get('/agent')
            ->assertOk()
            ->assertDontSee('فیلترهای من');

        $this->establish($agent);
        Filament::setCurrentPanel(Filament::getPanel('agent'));
        /** @var class-string<Component> $detailsComponent */
        $detailsComponent = AgentDetails::class;
        Livewire::actingAs($agent)->test($detailsComponent) // @phpstan-ignore argument.templateType
            ->assertSee('دسترسی سریع')
            ->assertSee('ملک همکار قابل مشاهده');
    }

    public function test_manager_can_create_property_with_embedded_owner_and_create_customer_from_wizards(): void
    {
        ['manager' => $manager] = $this->tenant();
        $this->establish($manager);
        Filament::setCurrentPanel(Filament::getPanel('agency'));
        /** @var class-string<Component> $propertyComponent */
        $propertyComponent = CreateProperty::class;

        Livewire::actingAs($manager)->test($propertyComponent) // @phpstan-ignore argument.templateType
            ->assertWizardStepExists(4)
            ->set('data', [
                'transaction_type' => 'sale',
                'property_type' => 'apartment',
                'title' => 'آپارتمان تست پنل',
                'status' => 'available',
                'city' => 'تهران',
                'district' => 'یوسف‌آباد',
                'area_sqm' => '100',
                'sale_price' => '10000000000',
                'sale_price_per_sqm' => '100000000',
                'price_input_mode' => 'total',
                'street_address' => 'خیابان نمونه، کوچه یکم',
                'plaque' => '۱۲',
                'delivery_status' => 'ready',
                'evacuation_date_display' => '۱۷ مرداد ۱۴۰۵',
                'bedrooms' => 2,
                'parking_spaces' => 1,
                'owner' => [
                    'full_name' => 'مالک آزمایشی',
                    'mobile' => '09120000001',
                    'phone' => null,
                    'notes' => 'ثبت از پنل بازطراحی‌شده',
                ],
                'new_images' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var class-string<Component> $customerComponent */
        $customerComponent = CreateCustomer::class;
        Livewire::actingAs($manager)->test($customerComponent) // @phpstan-ignore argument.templateType
            ->assertWizardStepExists(4)
            ->set('data', [
                'full_name' => 'مشتری آزمایشی',
                'mobile' => '09120000002',
                'intent' => 'buy',
                'desired_property_type' => 'apartment',
                'desired_city' => 'تهران',
                'desired_district' => 'یوسف‌آباد',
                'min_area_sqm' => '80',
                'max_area_sqm' => '120',
                'min_bedrooms' => 2,
                'budget_min' => '8000000000',
                'budget_max' => '12000000000',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('owners', ['full_name' => 'مالک آزمایشی', 'mobile' => '09120000001']);
        $this->assertDatabaseHas('properties', ['title' => 'آپارتمان تست پنل', 'year_built' => null]);
        $this->assertDatabaseHas('customers', ['full_name' => 'مشتری آزمایشی', 'assigned_agent_id' => null]);
    }
}
