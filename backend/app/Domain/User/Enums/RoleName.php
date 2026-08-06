<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case AgencyManager = 'agency-manager';
    case Agent = 'agent';

    /** @return list<PermissionName> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                PermissionName::PlatformDashboardView,
                PermissionName::AgenciesView,
                PermissionName::AgenciesCreate,
                PermissionName::AgenciesUpdate,
                PermissionName::AgenciesActivate,
                PermissionName::UsersView,
                PermissionName::UsersCreate,
                PermissionName::UsersUpdate,
                PermissionName::UsersDeactivate,
                PermissionName::UsersResetPassword,
                PermissionName::ProfileUpdate,
            ],
            self::AgencyManager => [
                PermissionName::AgencyDashboardView,
                PermissionName::AgencySettingsView,
                PermissionName::AgencySettingsUpdate,
                PermissionName::UsersView,
                PermissionName::UsersCreate,
                PermissionName::UsersUpdate,
                PermissionName::UsersDeactivate,
                PermissionName::UsersResetPassword,
                PermissionName::OwnersView,
                PermissionName::OwnersCreate,
                PermissionName::OwnersUpdate,
                PermissionName::OwnersDelete,
                PermissionName::OwnersRestore,
                PermissionName::PropertiesView,
                PermissionName::PropertiesCreate,
                PermissionName::PropertiesUpdate,
                PermissionName::PropertiesDelete,
                PermissionName::PropertiesRestore,
                PermissionName::PropertiesAssign,
                PermissionName::PropertiesChangeStatus,
                PermissionName::PropertiesManageImages,
                PermissionName::PropertiesManageNotes,
                PermissionName::CustomersView,
                PermissionName::CustomersCreate,
                PermissionName::CustomersUpdate,
                PermissionName::CustomersDelete,
                PermissionName::CustomersRestore,
                PermissionName::CustomersAssign,
                PermissionName::CustomersManageNotes,
                PermissionName::SavedFiltersManage,
                PermissionName::ReportsAgencyView,
                PermissionName::ReportsOwnView,
                PermissionName::ProfileUpdate,
            ],
            self::Agent => [
                PermissionName::AgencyDashboardView,
                PermissionName::OwnersView,
                PermissionName::OwnersCreate,
                PermissionName::OwnersUpdate,
                PermissionName::PropertiesView,
                PermissionName::PropertiesCreate,
                PermissionName::PropertiesUpdate,
                PermissionName::PropertiesChangeStatus,
                PermissionName::PropertiesManageImages,
                PermissionName::PropertiesManageNotes,
                PermissionName::CustomersView,
                PermissionName::CustomersCreate,
                PermissionName::CustomersUpdate,
                PermissionName::CustomersManageNotes,
                PermissionName::SavedFiltersManage,
                PermissionName::ReportsOwnView,
                PermissionName::ProfileUpdate,
            ],
        };
    }
}
