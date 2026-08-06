<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Application\Agency\Contracts\AgencyRepositoryContract;
use App\Domain\Agency\Exceptions\AgencyActivationException;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ActivateAgencyService
{
    public function __construct(private AgencyRepositoryContract $agencies) {}

    public function execute(User $actor, Agency $agency): void
    {
        if ($actor->roleName() !== RoleName::SuperAdmin
            || ! $actor->can(PermissionName::AgenciesActivate->value)) {
            throw new AuthorizationException;
        }

        if (! $this->agencies->hasActiveManager($agency)) {
            throw new AgencyActivationException('برای فعال‌سازی آژانس، وجود مدیر فعال الزامی است.');
        }

        $agency->is_active = true;
        $this->agencies->save($agency);
    }
}
