<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Exceptions\AgencyInactiveException;
use App\Domain\Tenancy\Exceptions\ImmutableTenantException;
use App\Domain\Tenancy\Exceptions\InactiveUserException;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Agency;
use App\Models\AgencySettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

final class TenantIsolationTest extends IdentityTestCase
{
    public function test_tenant_query_fails_before_sql_when_context_is_missing(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        try {
            AgencySettings::query()->get();
            self::fail('A tenant query unexpectedly ran without context.');
        } catch (MissingTenantContextException) {
            self::assertSame(0, $queries);
        }
    }

    public function test_scope_returns_only_the_established_agency_settings(): void
    {
        $agencyA = Agency::factory()->active()->create();
        $agencyB = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agencyA)->create();
        app(TenantContext::class)->establish($agent);

        $settings = AgencySettings::query()->get();

        self::assertCount(1, $settings);
        self::assertSame($agencyA->getKey(), $settings->first()?->agency_id);
        self::assertNull(AgencySettings::query()->find($agencyB->getKey()));
    }

    public function test_inactive_user_and_inactive_agency_are_rejected(): void
    {
        $activeAgency = Agency::factory()->active()->create();
        $inactiveUser = User::factory()->agent($activeAgency)->inactive()->create();

        $this->expectException(InactiveUserException::class);
        app(TenantContext::class)->establish($inactiveUser);
    }

    public function test_inactive_agency_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $agent = User::factory()->agent($agency)->create();

        $this->expectException(AgencyInactiveException::class);
        app(TenantContext::class)->establish($agent);
    }

    public function test_super_admin_cannot_open_a_tenant_scope(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        app(TenantContext::class)->establish($superAdmin);

        $this->expectException(MissingTenantContextException::class);
        AgencySettings::query()->get();
    }

    public function test_user_agency_is_immutable(): void
    {
        $agencyA = Agency::factory()->active()->create();
        $agencyB = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agencyA)->create();
        $agent->forceFill(['agency_id' => $agencyB->getKey()]);

        $this->expectException(ImmutableTenantException::class);
        $agent->save();
    }
}
