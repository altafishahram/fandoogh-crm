<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Widgets\AgencyStats;
use App\Http\Middleware\EnsureWebPasswordChanged;
use App\Http\Middleware\EstablishTenantContext;
use App\Livewire\MatchNotificationPoller;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use App\Providers\Filament\AgencyPanelProvider;
use App\Providers\Filament\AgentPanelProvider;
use App\Providers\Filament\ControlPanelProvider;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DomainTestCase;

final class LivewireTenantContextTest extends DomainTestCase
{
    public function test_all_panels_register_the_auth_stack_as_persistent_middleware(): void
    {
        $expected = [Authenticate::class, EstablishTenantContext::class, EnsureWebPasswordChanged::class];
        $property = new ReflectionProperty(Panel::class, 'livewirePersistentMiddleware');

        foreach ([AgencyPanelProvider::class, AgentPanelProvider::class, ControlPanelProvider::class] as $provider) {
            $panel = (new $provider($this->app))->panel(Panel::make());
            self::assertSame($expected, $panel->getAuthMiddleware());
            self::assertSame($expected, $property->getValue($panel));
        }

        foreach ($expected as $middleware) {
            self::assertContains($middleware, app(PersistentMiddleware::class)->getPersistentMiddleware());
        }
    }

    public function test_browser_poll_updates_restore_context_and_preserve_per_user_versioned_reads(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $first = $this->notification($agency, $manager, $agent);
        $second = $this->notification($agency, $manager, $agent, version: 2);
        MatchNotificationRead::query()->create([
            'match_notification_id' => $first->getKey(), 'user_id' => $manager->getKey(),
            'notification_version' => 1, 'read_at' => now(),
        ]);
        MatchNotificationRead::query()->create([
            'match_notification_id' => $second->getKey(), 'user_id' => $agent->getKey(),
            'notification_version' => 1, 'read_at' => now(),
        ]);
        app(TenantContext::class)->clear();
        ['agency' => $otherAgency, 'manager' => $otherManager, 'agent' => $otherAgent] = $this->tenant();
        $this->notification($otherAgency, $otherManager, $otherAgent);

        foreach ([[$manager, 'agency', 1], [$agent, 'agent', 2]] as [$actor, $panel, $expected]) {
            $page = $this->actingAs($actor)->get('/'.$panel)->assertOk();
            [$snapshot] = $this->componentSnapshot((string) $page->getContent(), MatchNotificationPoller::class);
            $response = $this->updateComponent($snapshot, 'refreshUnread')->assertOk();
            $updated = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame($expected, $updated['data']['unreadCount']);
            self::assertSame($expected, $updated['data']['lastKnownCount']);
            self::assertSame($agency->getKey(), app(TenantContext::class)->agencyId());
            self::assertSame($actor->getKey(), app(TenantContext::class)->user()->getKey());
            self::assertSame($panel, Filament::getCurrentPanel()?->getId());
        }

        $this->assertDatabaseCount('match_notification_reads', 2);
    }

    public function test_direct_refresh_reestablishes_a_cleared_or_wrong_tenant_context_and_sends_new_notifications(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->notification($agency, $manager, $agent);
        Filament::setCurrentPanel(Filament::getPanel('agency'));
        $this->actingAs($manager);
        $component = Livewire::test(MatchNotificationPoller::class)
            ->assertSet('unreadCount', 1);

        app(TenantContext::class)->clear();
        $component->call('refreshUnread')->assertSet('unreadCount', 1);
        Notification::assertNotNotified();

        $this->notification($agency, $manager, $agent);
        app(TenantContext::class)->clear();
        ['agency' => $otherAgency, 'manager' => $otherManager, 'agent' => $otherAgent] = $this->tenant();
        $this->notification($otherAgency, $otherManager, $otherAgent);
        $component->call('refreshUnread')->assertSet('unreadCount', 2)->assertSet('lastKnownCount', 2);
        self::assertSame($agency->getKey(), app(TenantContext::class)->agencyId());
        Notification::assertNotified('1 اعلان تطبیق جدید دریافت شد');
        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_lazy_agency_stats_load_over_http_with_fresh_tenant_context(): void
    {
        ['manager' => $manager] = $this->tenant();
        $page = $this->actingAs($manager)->get('/agency')->assertOk();
        [$snapshot, $tag] = $this->componentSnapshot((string) $page->getContent(), AgencyStats::class);
        $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($state['memo']['lazyLoaded']);
        $response = $this->updateComponent($snapshot, '__lazyLoad', [$this->lazyMountParams($tag)])->assertOk();
        self::assertStringContainsString('املاک موجود', $response->json('components.0.effects.html'));
        self::assertStringContainsString('املاک بدون کارشناس', $response->json('components.0.effects.html'));
        $updated = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($updated['memo']['lazyLoaded']);
        self::assertSame($manager->agency_id, app(TenantContext::class)->agencyId());
    }

    /** @return iterable<string, array{string, class-string}> */
    public static function revokedAccess(): iterable
    {
        foreach (['agency', 'user', 'role', 'super-admin'] as $reason) {
            yield $reason.' poll' => [$reason, MatchNotificationPoller::class];
            yield $reason.' lazy stats' => [$reason, AgencyStats::class];
        }
    }

    #[DataProvider('revokedAccess')]
    public function test_browser_updates_reject_access_invalidated_after_initial_render(string $reason, string $component): void
    {
        ['agency' => $agency, 'manager' => $manager] = $this->tenant();
        $page = $this->actingAs($manager)->get('/agency')->assertOk();
        [$snapshot, $tag] = $this->componentSnapshot((string) $page->getContent(), $component);
        $params = $component === AgencyStats::class ? [$this->lazyMountParams($tag)] : [];

        match ($reason) {
            'agency' => $agency->update(['is_active' => false]),
            'user' => $manager->update(['is_active' => false]),
            'role' => $manager->syncRoles([RoleName::Agent->value]),
            'super-admin' => $manager->syncRoles([RoleName::SuperAdmin->value]),
            default => throw new \LogicException('Unsupported revocation reason.'),
        };
        // A new HTTP request reloads its authenticated user from the session.
        $this->actingAs($manager->refresh());
        $this->updateComponent($snapshot, $component === AgencyStats::class ? '__lazyLoad' : 'refreshUnread', $params)
            ->assertForbidden();
    }

    public function test_password_change_requirement_is_replayed_on_browser_poll_updates(): void
    {
        ['manager' => $manager] = $this->tenant();
        $page = $this->actingAs($manager)->get('/agency')->assertOk();
        [$snapshot] = $this->componentSnapshot((string) $page->getContent(), MatchNotificationPoller::class);
        $manager->update(['must_change_password' => true]);
        $this->actingAs($manager->refresh());
        $this->updateComponent($snapshot, 'refreshUnread')->assertRedirect('/agency/profile');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDirectAccess(): iterable
    {
        foreach (['inactive-user', 'deleted-user', 'inactive-agency', 'no-role', 'unsupported-role', 'multiple-roles', 'super-admin', 'wrong-panel', 'no-panel', 'guest'] as $reason) {
            yield $reason => [$reason];
        }
    }

    #[DataProvider('invalidDirectAccess')]
    public function test_direct_refresh_never_queries_notifications_for_an_invalid_actor_or_panel(string $reason): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->notification($agency, $manager, $agent);
        Filament::setCurrentPanel(Filament::getPanel('agency'));
        $this->actingAs($manager);
        $poller = new MatchNotificationPoller;
        $poller->mount();
        self::assertSame(1, $poller->unreadCount);
        $manager->load(['agency', 'roles']);

        match ($reason) {
            'inactive-user' => User::query()->whereKey($manager->getKey())->update(['is_active' => false]),
            'deleted-user' => User::query()->whereKey($manager->getKey())->delete(),
            'inactive-agency' => $agency->update(['is_active' => false]),
            'no-role' => $manager->syncRoles([]),
            'unsupported-role' => $manager->syncRoles([Role::findOrCreate('unsupported-poller-role', 'web')]),
            'multiple-roles' => $manager->assignRole(RoleName::Agent->value),
            'super-admin' => $this->actingAs(User::factory()->superAdmin()->create()),
            'wrong-panel' => Filament::setCurrentPanel(Filament::getPanel('agent')),
            'no-panel' => Filament::setCurrentPanel(null),
            'guest' => auth()->logout(),
            default => throw new \LogicException('Unsupported access reason.'),
        };

        $poller->refreshUnread();
        self::assertSame(0, $poller->unreadCount);
        self::assertSame(0, $poller->lastKnownCount);
        self::assertFalse(app(TenantContext::class)->hasAgency());
        Notification::assertNotNotified();
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

        self::fail('The dashboard did not render a snapshot for '.$component);
    }

    private function lazyMountParams(string $tag): string
    {
        preg_match('/__lazyLoad\(\x27([A-Za-z0-9+\/=]+)\x27\)/', html_entity_decode($tag, ENT_QUOTES, 'UTF-8'), $matches);
        self::assertNotEmpty($matches[1] ?? null, 'Lazy mount parameters must come from the actual dashboard.');

        return $matches[1] ?? throw new \LogicException('Missing lazy mount parameters.');
    }

    /**
     * @param  list<mixed>  $params
     * @return TestResponse<Response>
     */
    private function updateComponent(string $snapshot, string $method, array $params = []): TestResponse
    {
        // Simulate a separate browser request, with no panel or tenant left over
        // from the initial GET. Keep the original signed snapshot untouched.
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

    private function notification(Agency $agency, User $manager, User $agent, int $version = 1): MatchNotification
    {
        $this->establish($manager);
        $property = Property::factory()->forAgency($agency, $manager, $agent)->create();
        $customer = Customer::factory()->forAgency($agency, $manager, $agent)->create();
        $match = PropertyCustomerMatch::query()->create([
            'property_id' => $property->getKey(), 'customer_id' => $customer->getKey(),
            'score' => 80, 'financial_score' => 44, 'area_score' => 20, 'feature_score' => 16,
            'match_mode' => 'direct', 'property_rank' => 1, 'customer_rank' => 1,
            'fingerprint' => hash('sha256', $property->getKey().':'.$customer->getKey()),
        ]);

        return MatchNotification::query()->create([
            'property_customer_match_id' => $match->getKey(), 'version' => $version,
            'title' => 'تطبیق آزمایشی', 'body' => 'اعلان آزمایشی برای بررسی بافت آژانس.',
        ]);
    }
}
