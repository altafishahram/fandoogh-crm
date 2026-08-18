<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum PermissionName: string
{
    case PlatformDashboardView = 'platform.dashboard.view';
    case AgenciesView = 'agencies.view';
    case AgenciesCreate = 'agencies.create';
    case AgenciesUpdate = 'agencies.update';
    case AgenciesActivate = 'agencies.activate';
    case AgencyDashboardView = 'agency.dashboard.view';
    case AgencySettingsView = 'agency.settings.view';
    case AgencySettingsUpdate = 'agency.settings.update';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDeactivate = 'users.deactivate';
    case UsersResetPassword = 'users.reset_password';
    case OwnersView = 'owners.view';
    case OwnersCreate = 'owners.create';
    case OwnersUpdate = 'owners.update';
    case OwnersDelete = 'owners.delete';
    case OwnersRestore = 'owners.restore';
    case PropertiesView = 'properties.view';
    case PropertiesCreate = 'properties.create';
    case PropertiesUpdate = 'properties.update';
    case PropertiesDelete = 'properties.delete';
    case PropertiesRestore = 'properties.restore';
    case PropertiesAssign = 'properties.assign';
    case PropertiesChangeStatus = 'properties.change_status';
    case PropertiesManageImages = 'properties.manage_images';
    case PropertiesManageNotes = 'properties.manage_notes';
    case PropertyImagesView = 'property_images.view';
    case PropertyImagesCreate = 'property_images.create';
    case PropertyImagesUpdate = 'property_images.update';
    case PropertyImagesDelete = 'property_images.delete';
    case PropertyNotesView = 'property_notes.view';
    case PropertyNotesCreate = 'property_notes.create';
    case PropertyNotesUpdate = 'property_notes.update';
    case PropertyNotesDelete = 'property_notes.delete';
    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersChangeStatus = 'customers.change_status';
    case CustomersDelete = 'customers.delete';
    case CustomersRestore = 'customers.restore';
    case CustomersAssign = 'customers.assign';
    case CustomersManageNotes = 'customers.manage_notes';
    case CustomerNotesView = 'customer_notes.view';
    case CustomerNotesCreate = 'customer_notes.create';
    case CustomerNotesUpdate = 'customer_notes.update';
    case CustomerNotesDelete = 'customer_notes.delete';
    case SavedFiltersManage = 'saved_filters.manage';
    case ReportsAgencyView = 'reports.agency.view';
    case ReportsOwnView = 'reports.own.view';
    case ProfileUpdate = 'profile.update';
}
