<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;

final class PermissionLabels
{
    /** @return array<string, string> */
    public static function agentOptions(): array
    {
        $labels = [
            PermissionName::OwnersView->value => 'مشاهده اطلاعات مالک در ملک',
            PermissionName::OwnersCreate->value => 'ثبت اطلاعات مالک همراه ملک',
            PermissionName::OwnersUpdate->value => 'ویرایش اطلاعات مالک ملک',
            PermissionName::PropertiesView->value => 'مشاهده املاک',
            PermissionName::PropertiesCreate->value => 'ثبت ملک',
            PermissionName::PropertiesUpdate->value => 'ویرایش ملک',
            PermissionName::PropertiesDelete->value => 'حذف ملک',
            PermissionName::PropertiesRestore->value => 'بازیابی ملک',
            PermissionName::PropertiesChangeStatus->value => 'تغییر وضعیت ملک',
            PermissionName::PropertyImagesView->value => 'مشاهده تصاویر ملک',
            PermissionName::PropertyImagesCreate->value => 'افزودن تصویر ملک',
            PermissionName::PropertyImagesUpdate->value => 'تغییر ترتیب تصاویر ملک',
            PermissionName::PropertyImagesDelete->value => 'حذف تصویر ملک',
            PermissionName::PropertyNotesView->value => 'مشاهده یادداشت‌های ملک',
            PermissionName::PropertyNotesCreate->value => 'ثبت یادداشت ملک',
            PermissionName::PropertyNotesUpdate->value => 'ویرایش یادداشت ملک',
            PermissionName::PropertyNotesDelete->value => 'حذف یادداشت ملک',
            PermissionName::CustomersView->value => 'مشاهده مشتریان',
            PermissionName::CustomersCreate->value => 'ثبت مشتری',
            PermissionName::CustomersUpdate->value => 'ویرایش مشتری',
            PermissionName::CustomersDelete->value => 'حذف مشتری',
            PermissionName::CustomersRestore->value => 'بازیابی مشتری',
            PermissionName::CustomersChangeStatus->value => 'تغییر وضعیت مشتری',
            PermissionName::CustomerNotesView->value => 'مشاهده یادداشت‌های مشتری',
            PermissionName::CustomerNotesCreate->value => 'ثبت یادداشت مشتری',
            PermissionName::CustomerNotesUpdate->value => 'ویرایش یادداشت مشتری',
            PermissionName::CustomerNotesDelete->value => 'حذف یادداشت مشتری',
            PermissionName::SavedFiltersManage->value => 'مدیریت فیلترهای شخصی',
            PermissionName::ReportsOwnView->value => 'مشاهده گزارش‌ها',
        ];

        $options = [];
        foreach (RoleName::agentConfigurablePermissions() as $permission) {
            $options[$permission->value] = $labels[$permission->value] ?? $permission->value;
        }

        return $options;
    }
}
