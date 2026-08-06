<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Application\Agency\Contracts\AgencyRepositoryContract;
use App\Application\Agency\Data\ProvisionAgencyData;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ProvisionAgencyService
{
    public function __construct(private AgencyRepositoryContract $agencies) {}

    public function execute(User $actor, ProvisionAgencyData $data): Agency
    {
        if (! $actor->is_active
            || $actor->roleName() !== RoleName::SuperAdmin
            || ! $actor->can(PermissionName::AgenciesCreate->value)) {
            throw new AuthorizationException;
        }

        if (! preg_match('/^[A-Z0-9]{2,10}$/', mb_strtoupper(trim($data->propertyCodePrefix)))) {
            throw new DomainConflictException('پیشوند کد ملک باید شامل ۲ تا ۱۰ حرف بزرگ انگلیسی یا عدد باشد.');
        }

        if (! in_array($data->defaultPageSize, [10, 25, 50, 100], true)) {
            throw new DomainConflictException('تعداد پیش‌فرض در هر صفحه پشتیبانی نمی‌شود.');
        }

        return DB::transaction(function () use ($data): Agency {
            $agency = $this->agencies->create($data);
            $this->agencies->createSettings($agency, $data);

            return $agency;
        });
    }
}
