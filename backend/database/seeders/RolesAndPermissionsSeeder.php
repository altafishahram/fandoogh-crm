<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissionNames = array_map(
                static fn (PermissionName $permission): string => $permission->value,
                PermissionName::cases(),
            );
            $roleNames = array_map(
                static fn (RoleName $role): string => $role->value,
                RoleName::cases(),
            );

            Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $permissionNames)
                ->delete();

            Role::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $roleNames)
                ->delete();

            foreach ($permissionNames as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::findOrCreate($roleName->value, 'web');
                $role->syncPermissions(array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    $roleName->permissions(),
                ));
            }

            $agentDefaults = array_map(
                static fn (PermissionName $permission): string => $permission->value,
                RoleName::agentDefaultPermissions(),
            );
            User::role(RoleName::Agent->value)
                ->doesntHave('permissions')
                ->each(static fn (User $agent) => $agent->givePermissionTo($agentDefaults));

            DB::table('model_has_permissions')
                ->whereNotIn('permission_id', Permission::query()->pluck('id'))
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
