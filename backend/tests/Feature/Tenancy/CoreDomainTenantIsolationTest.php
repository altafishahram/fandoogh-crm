<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Owner;
use Illuminate\Support\Facades\DB;
use Tests\Support\DomainTestCase;

final class CoreDomainTenantIsolationTest extends DomainTestCase
{
    public function test_operational_query_fails_closed_without_tenant_before_sql(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        try {
            Owner::query()->count();
            $this->fail('Tenant-owned query should fail closed.');
        } catch (MissingTenantContextException) {
            $this->assertSame(0, $queries);
        }
    }

    public function test_operational_scope_hides_other_agency_records(): void
    {
        ['agency' => $firstAgency, 'manager' => $firstManager] = $this->tenant();
        $owner = Owner::factory()->forAgency($firstAgency, $firstManager)->create();
        app(TenantContext::class)->clear();
        ['manager' => $otherManager] = $this->tenant();
        $this->establish($otherManager);

        $this->assertNull(Owner::query()->find($owner->getKey()));
    }
}
