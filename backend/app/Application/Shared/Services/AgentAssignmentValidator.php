<?php

declare(strict_types=1);

namespace App\Application\Shared\Services;

use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\User;

final class AgentAssignmentValidator
{
    public function validate(int $agentId, int $agencyId): User
    {
        $agent = User::query()
            ->whereKey($agentId)
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->first();

        if (! $agent instanceof User || $agent->roleName() !== RoleName::Agent) {
            throw new DomainConflictException('کارشناس مسئول باید کاربر فعال همین آژانس باشد.');
        }

        return $agent;
    }
}
