<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\RoleName;
use App\Models\CustomerNote;
use App\Models\User;

final class CustomerNotePolicy
{
    public function update(User $user, CustomerNote $note): bool
    {
        return $user->agency_id === $note->agency_id
            && ($user->roleName() === RoleName::AgencyManager
                || $note->author_user_id === $user->getKey());
    }

    public function delete(User $user, CustomerNote $note): bool
    {
        return $this->update($user, $note);
    }
}
