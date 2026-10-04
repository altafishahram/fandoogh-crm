<?php

declare(strict_types=1);

namespace App\Filament\Control\Support;

use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Filament\Facades\Filament;

final class PlatformPanelAccess
{
    public static function actor(): User
    {
        $context = app(TenantContext::class);
        $context->clear();
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $user = $user->fresh();
        abort_unless($user instanceof User && $user->is_active && ! $user->trashed()
            && $user->agency_id === null, 403);
        $roles = $user->roles()->pluck('name');
        abort_unless($roles->count() === 1 && $roles->first() === RoleName::SuperAdmin->value, 403);
        $panel = Filament::getCurrentPanel();
        abort_unless($panel !== null && $panel->getId() === 'control' && $user->canAccessPanel($panel), 403);
        $context->establish($user);

        return $user;
    }
}
