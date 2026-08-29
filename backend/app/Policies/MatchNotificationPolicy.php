<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\RoleName;
use App\Models\MatchNotification;
use App\Models\User;

final class MatchNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canUse($user);
    }

    public function view(User $user, MatchNotification $notification): bool
    {
        return $this->canUse($user)
            && (int) $user->agency_id === (int) $notification->getAttribute('agency_id');
    }

    public function markAsRead(User $user, MatchNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    private function canUse(User $user): bool
    {
        if (! $user->is_active || $user->agency_id === null) {
            return false;
        }

        return in_array($user->roleName(), [RoleName::Agent, RoleName::AgencyManager], true);
    }
}
