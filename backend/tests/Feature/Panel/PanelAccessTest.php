<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Filament\Shared\Pages\Profile;
use App\Models\Agency;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
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

        $component = Livewire::actingAs($superAdmin)
            ->test(Profile::class)
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
}
