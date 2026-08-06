<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\Agency;
use App\Models\User;
use Tests\Support\IdentityTestCase;

final class IdentityPolicyTest extends IdentityTestCase
{
    public function test_manager_can_manage_only_agents_from_the_same_agency(): void
    {
        $agencyA = Agency::factory()->active()->create();
        $agencyB = Agency::factory()->active()->create();
        $manager = User::factory()->agencyManager($agencyA)->create();
        $sameTenantAgent = User::factory()->agent($agencyA)->create();
        $crossTenantAgent = User::factory()->agent($agencyB)->create();

        self::assertTrue($manager->can('update', $sameTenantAgent));
        self::assertFalse($manager->can('update', $crossTenantAgent));
        self::assertFalse($manager->can('deactivate', $manager));
    }

    public function test_super_admin_can_manage_managers_but_not_tenant_agents(): void
    {
        $agency = Agency::factory()->active()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = User::factory()->agencyManager($agency)->create();
        $agent = User::factory()->agent($agency)->create();

        self::assertTrue($superAdmin->can('update', $manager));
        self::assertFalse($superAdmin->can('update', $agent));
    }
}
