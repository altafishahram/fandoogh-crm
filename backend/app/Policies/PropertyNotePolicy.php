<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Enums\RoleName;
use App\Models\PropertyNote;
use App\Models\User;

final class PropertyNotePolicy
{
    public function update(User $user, PropertyNote $note): bool
    {
        return $user->agency_id === $note->agency_id
            && ($user->roleName() === RoleName::AgencyManager
                || $note->author_user_id === $user->getKey());
    }

    public function delete(User $user, PropertyNote $note): bool
    {
        return $this->update($user, $note);
    }
}
