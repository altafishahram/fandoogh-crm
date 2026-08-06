<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Application\Agency\Contracts\AgencyRepositoryContract;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SuspendAgencyService
{
    public function __construct(private AgencyRepositoryContract $agencies) {}

    public function execute(User $actor, Agency $agency): void
    {
        if ($actor->roleName() !== RoleName::SuperAdmin
            || ! $actor->can(PermissionName::AgenciesActivate->value)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($agency): void {
            $agency->is_active = false;
            $this->agencies->save($agency);
            $this->agencies->revokeAllMobileTokens($agency);
        });
    }
}
