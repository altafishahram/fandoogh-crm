<?php

declare(strict_types=1);

namespace App\Application\Agency\Services;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;

final class VerifyAgencyService
{
    public function execute(User $actor, Agency $agency, bool $verified): Agency
    {
        abort_unless($actor->is_active && $actor->roleName() === RoleName::SuperAdmin && $actor->can(PermissionName::AgenciesVerify->value), 403);
        $agency->forceFill(['is_verified' => $verified, 'verified_at' => $verified ? now() : null, 'verified_by_user_id' => $actor->getKey()])->save();

        return $agency->refresh();
    }
}
