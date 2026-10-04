<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Add marketplace capabilities while preserving existing role and user grants. */
final class MarketplacePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                [PermissionName::AgenciesVerify, RoleName::SuperAdmin],
                [PermissionName::PropertiesPublish, RoleName::AgencyManager],
            ] as [$permissionName, $roleName]) {
                $permission = Permission::findOrCreate($permissionName->value, 'web');
                Role::findOrCreate($roleName->value, 'web')->givePermissionTo($permission);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
