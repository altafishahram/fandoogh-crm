<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

final class RolesAndPermissionsSeederTest extends IdentityTestCase
{
    public function test_seeder_synchronizes_the_exact_role_permission_catalog(): void
    {
        self::assertSame(count(PermissionName::cases()), Permission::query()->count());
        self::assertSame(count(RoleName::cases()), Role::query()->count());

        foreach (RoleName::cases() as $roleName) {
            $actual = Role::findByName($roleName->value, 'web')
                ->permissions
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
            $expected = collect($roleName->permissions())
                ->map(static fn (PermissionName $permission): string => $permission->value)
                ->sort()
                ->values()
                ->all();

            self::assertSame($expected, $actual);
        }

        self::assertSame(0, DB::table('model_has_permissions')->count());
    }

    public function test_seeder_is_idempotent_and_removes_stale_or_direct_permissions(): void
    {
        $user = User::factory()->create();
        $stalePermission = Permission::create(['name' => 'stale.permission', 'guard_name' => 'web']);
        Role::create(['name' => 'stale-role', 'guard_name' => 'web']);
        $user->givePermissionTo($stalePermission);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        self::assertFalse(Permission::query()->where('name', 'stale.permission')->exists());
        self::assertFalse(Role::query()->where('name', 'stale-role')->exists());
        self::assertSame(0, DB::table('model_has_permissions')->count());
        self::assertSame(count(PermissionName::cases()), Permission::query()->count());
    }
}
