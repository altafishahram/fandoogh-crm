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
                PermissionName::PropertyImagesView,
                PermissionName::PropertyImagesCreate,
                PermissionName::PropertyImagesUpdate,
                PermissionName::PropertyImagesDelete,
                PermissionName::PropertyNotesView,
                PermissionName::PropertyNotesCreate,
                PermissionName::PropertyNotesUpdate,
                PermissionName::PropertyNotesDelete,
                PermissionName::CustomersView,
                PermissionName::CustomersCreate,
                PermissionName::CustomersUpdate,
                PermissionName::CustomersChangeStatus,
                PermissionName::CustomersDelete,
                PermissionName::CustomersRestore,
                PermissionName::CustomersAssign,
                PermissionName::CustomersManageNotes,
                PermissionName::CustomerNotesView,
                PermissionName::CustomerNotesCreate,
                PermissionName::CustomerNotesUpdate,
                PermissionName::CustomerNotesDelete,
                PermissionName::SavedFiltersManage,
                PermissionName::ReportsAgencyView,
                PermissionName::ReportsOwnView,
                PermissionName::ProfileUpdate,
            ],
            self::Agent => [
                PermissionName::AgencyDashboardView,
                PermissionName::ProfileUpdate,
            ],
        };
    }

    /** @return list<PermissionName> */
    public static function agentDefaultPermissions(): array
    {
        return [
            PermissionName::OwnersView,
            PermissionName::OwnersCreate,
            PermissionName::OwnersUpdate,
            PermissionName::PropertiesView,
            PermissionName::PropertiesCreate,
            PermissionName::PropertiesUpdate,
            PermissionName::PropertyImagesView,
            PermissionName::PropertyImagesCreate,
            PermissionName::PropertyImagesUpdate,
            PermissionName::PropertyImagesDelete,
            PermissionName::PropertyNotesView,
            PermissionName::PropertyNotesCreate,
            PermissionName::PropertyNotesUpdate,
            PermissionName::PropertyNotesDelete,
            PermissionName::CustomersView,
            PermissionName::CustomersCreate,
            PermissionName::CustomersUpdate,
            PermissionName::CustomerNotesView,
            PermissionName::CustomerNotesCreate,
            PermissionName::CustomerNotesUpdate,
            PermissionName::CustomerNotesDelete,
            PermissionName::SavedFiltersManage,
            PermissionName::ReportsOwnView,
        ];
    }

    /** @return list<PermissionName> */
    public static function agentConfigurablePermissions(): array
    {
        return [
            ...self::agentDefaultPermissions(),
            PermissionName::PropertiesDelete,
            PermissionName::PropertiesRestore,
            PermissionName::PropertiesChangeStatus,
            PermissionName::CustomersDelete,
            PermissionName::CustomersRestore,
            PermissionName::CustomersChangeStatus,
        ];
    }
}
