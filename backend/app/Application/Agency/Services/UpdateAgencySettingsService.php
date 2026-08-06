<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\AgencyScope;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\AgencySettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class UpdateAgencySettingsService
{
    public function execute(User $actor, Agency $agency, string $prefix, int $pageSize): AgencySettings
    {
        $platformUpdate = $actor->roleName() === RoleName::SuperAdmin
            && $actor->can(PermissionName::AgenciesUpdate->value);
        $tenantUpdate = $actor->roleName() === RoleName::AgencyManager
            && $actor->agency_id === $agency->getKey()
            && $actor->can(PermissionName::AgencySettingsUpdate->value);
        if (! $actor->is_active || (! $platformUpdate && ! $tenantUpdate)) {
            throw new AuthorizationException;
        }

        $settings = AgencySettings::withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agency->getKey())->firstOrFail();
        $normalizedPrefix = mb_strtoupper(trim($prefix));
        if ($settings->next_property_sequence > 1 && $settings->property_code_prefix !== $normalizedPrefix) {
            throw new DomainConflictException('پس از ثبت اولین ملک، پیشوند کد ملک قابل تغییر نیست.');
        }
        if (! preg_match('/^[A-Z0-9]{2,10}$/', $normalizedPrefix)) {
            throw new DomainConflictException('پیشوند کد ملک باید شامل ۲ تا ۱۰ حرف بزرگ انگلیسی یا عدد باشد.');
        }
        if (! in_array($pageSize, [10, 25, 50, 100], true)) {
            throw new DomainConflictException('تعداد پیش‌فرض در هر صفحه پشتیبانی نمی‌شود.');
        }

        $settings->fill([
            'property_code_prefix' => $normalizedPrefix,
            'default_page_size' => $pageSize,
        ])->save();

        return $settings;
    }
}
