<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Filament\Control\Widgets\PlatformDetails;
use App\Filament\Control\Widgets\PlatformStats;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DomainTestCase;

final class ControlPanelRegressionTest extends DomainTestCase
{
    public function test_platform_details_show_only_platform_actions_and_authorized_agency_totals(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $agency->update(['name' => 'آژانس آزمایش داشبورد']);
        $this->establish($manager);
        Property::factory()->forAgency($agency, $manager, $agent)->create(['title' => 'عنوان خصوصی ملک']);
        Customer::factory()->forAgency($agency, $manager, $agent)->create(['full_name' => 'نام خصوصی مشتری']);
        $admin = User::factory()->superAdmin()->create();
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->actingAs($admin);
        Livewire::test(PlatformDetails::class)
            ->assertSee('ثبت آژانس جدید')->assertSee('ثبت مدیر آژانس')
            ->assertSee('آژانس آزمایش داشبورد')->assertSee('آمار آژانس‌ها')
            ->assertDontSee('املاک اخیر')->assertDontSee('آخرین یادداشت‌ها')
            ->assertDontSee('عنوان خصوصی ملک')->assertDontSee('نام خصوصی مشتری')
            ->assertSet('urls.agencies', url('/control/agencies'))
            ->assertSet('urls.manager_create', url('/control/users/create'))
            ->assertSet('data.agency_totals.0.properties', 1)
            ->assertSet('data.agency_totals.0.customers', 1);

        self::assertFalse(app(TenantContext::class)->hasAgency());
        self::assertSame($admin->getKey(), app(TenantContext::class)->user()->getKey());
    }

    public function test_platform_details_refresh_over_a_fresh_browser_request(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $page = $this->actingAs($admin)->get('/control')->assertOk();
        [$snapshot, $tag] = $this->componentSnapshot((string) $page->getContent(), PlatformDetails::class);
        $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        if (($state['memo']['lazyLoaded'] ?? true) === false) {
            $response = $this->updateComponent($snapshot, '__lazyLoad', [$this->lazyMountParams($tag)])->assertOk();
            $snapshot = $response->json('components.0.snapshot');
        }
        $response = $this->updateComponent($snapshot, 'refreshPlatform')->assertOk();
        self::assertStringContainsString('ثبت آژانس جدید', $response->json('components.0.effects.html'));
        self::assertStringNotContainsString('/agency/properties', $response->json('components.0.effects.html'));
        self::assertStringNotContainsString('هنوز ملکی ثبت نشده', $response->json('components.0.effects.html'));
        self::assertFalse(app(TenantContext::class)->hasAgency());
        self::assertSame($admin->getKey(), app(TenantContext::class)->user()->getKey());
    }

    public function test_lazy_platform_stats_load_over_http_without_tenant_record_access(): void
    {
        $this->tenant();
        Agency::factory()->suspended()->create();
        $admin = User::factory()->superAdmin()->create();
        $page = $this->actingAs($admin)->get('/control')->assertOk();
        [$snapshot, $tag] = $this->componentSnapshot((string) $page->getContent(), PlatformStats::class);
        $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($state['memo']['lazyLoaded']);
        $response = $this->updateComponent($snapshot, '__lazyLoad', [$this->lazyMountParams($tag)])->assertOk();
        foreach (['آژانس‌های فعال', 'آژانس‌های تعلیق‌شده', 'مدیران فعال', 'کارشناسان فعال'] as $label) {
            self::assertStringContainsString($label, $response->json('components.0.effects.html'));
        }
        self::assertFalse(app(TenantContext::class)->hasAgency());
        self::assertSame($admin->getKey(), app(TenantContext::class)->user()->getKey());
    }

    /** @return iterable<string, array{string, class-string}> */
    public static function invalidBrowserActors(): iterable
    {
        foreach (['manager', 'agent', 'inactive'] as $actor) {
            foreach ([PlatformDetails::class, PlatformStats::class] as $component) {
                yield $actor.' '.$component => [$actor, $component];
            }
        }
    }

    #[DataProvider('invalidBrowserActors')]
    public function test_browser_hydration_denies_manager_agent_and_inactive_admin(string $actor, string $component): void
    {
        ['manager' => $manager, 'agent' => $agent] = $this->tenant();
        $admin = User::factory()->superAdmin()->create();
        $page = $this->actingAs($admin)->get('/control')->assertOk();
        [$snapshot, $tag] = $this->componentSnapshot((string) $page->getContent(), $component);
        $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        $lazy = ($state['memo']['lazyLoaded'] ?? true) === false;
        $params = $lazy ? [$this->lazyMountParams($tag)] : [];
        if ($actor === 'inactive') {
            $admin->update(['is_active' => false]);
        }
        $this->actingAs(match ($actor) {
            'manager' => $manager, 'agent' => $agent, 'inactive' => $admin->refresh(),
            default => throw new \LogicException('Unsupported actor.'),
        });
        $this->updateComponent($snapshot, $lazy ? '__lazyLoad' : 'refreshPlatform', $params)->assertForbidden();
    }

    public function test_tenant_roles_cannot_open_control_routes_and_admin_cannot_open_tenant_records(): void
    {
        ['manager' => $manager, 'agent' => $agent] = $this->tenant();
        foreach ([$manager, $agent] as $user) {
            $this->actingAs($user);
            foreach (['/control', '/control/agencies', '/control/users'] as $path) {
                $this->get($path)->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->superAdmin()->create());
        foreach (['/agency/properties', '/agency/customers', '/agent/properties', '/agent/customers'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_details_and_stats_reject_a_revoked_role_even_with_a_stale_authenticated_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs($admin);
        $details = Livewire::test(PlatformDetails::class);
        $stats = Livewire::test(PlatformStats::class);
        $admin->load('roles');
        $admin->syncRoles([RoleName::AgencyManager->value]);

        $details->call('refreshPlatform')->assertForbidden();
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $stats->call('$refresh')->assertForbidden();
        self::assertFalse(app(TenantContext::class)->hasAgency());
    }

    public function test_all_control_resource_pages_render_and_only_agency_managers_are_listed(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $manager->update(['name' => 'مدیر قابل مدیریت']);
        $agent->update(['name' => 'کارشناس غیرقابل مدیریت کل']);
        $admin = User::factory()->superAdmin()->create(['name' => 'مدیر کل غیرقابل مدیریت']);
        $this->actingAs($admin);
        foreach ([
            '/control', '/control/profile', '/control/agencies', '/control/agencies/create',
            '/control/agencies/'.$agency->getKey(), '/control/agencies/'.$agency->getKey().'/edit',
            '/control/users/create', '/control/users/'.$manager->getKey(), '/control/users/'.$manager->getKey().'/edit',
        ] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/control/users')->assertOk()->assertSee('مدیر قابل مدیریت')
            ->assertDontSee('کارشناس غیرقابل مدیریت کل');
        $this->get('/control/users/'.$agent->getKey())->assertNotFound();
        $this->get('/control/users/'.$admin->getKey())->assertNotFound();
    }

    /** @return array{string, string} */
    private function componentSnapshot(string $html, string $component): array
    {
        $name = Livewire::new($component)->getName();
        preg_match_all('/<[^>]*\bwire:snapshot="([^"]+)"[^>]*>/', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $snapshot = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
            $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
            if ($state['memo']['name'] === $name) {
                return [$snapshot, $match[0]];
            }
        }
        self::fail('Missing actual dashboard snapshot for '.$component);
    }

    private function lazyMountParams(string $tag): string
    {
        preg_match('/__lazyLoad\(\x27([A-Za-z0-9+\/=]+)\x27\)/', html_entity_decode($tag, ENT_QUOTES, 'UTF-8'), $matches);
        self::assertNotEmpty($matches[1] ?? null);

        return $matches[1] ?? throw new \LogicException('Missing lazy mount parameters.');
    }

    /**
     * @param  list<mixed>  $params
     * @return TestResponse<Response>
     */
    private function updateComponent(string $snapshot, string $method, array $params = []): TestResponse
    {
        Livewire::flushState();
        app(TenantContext::class)->clear();
        Filament::setCurrentPanel(null);

        return $this->postJson(app(HandleRequests::class)->getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot, 'updates' => [],
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
            ]],
        ], ['X-Livewire' => 'true']);
    }
}
