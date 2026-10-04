<?php

declare(strict_types=1);

namespace App\Application\Marketplace\Services;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\User\Enums\RoleName;
use App\Models\PropertyPublication;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ListingAccessService
{
    public function assertAudience(User|PublicUser $actor, string $audience): void
    {
        abort_unless(in_array($audience, ['agency', 'public'], true), 422);
        abort_unless($actor->is_active, 403);
        if ($actor instanceof User) {
            abort_unless(! $actor->trashed() && ! $actor->must_change_password && in_array($actor->roleName(), [RoleName::AgencyManager, RoleName::Agent], true)
                && $actor->agency?->is_active, 403);
        }
        if ($audience === 'agency') {
            abort_unless($actor instanceof User && $actor->agency?->is_verified, 403);
        }
    }

    /** @return Builder<PropertyPublication> */
    public function visibleQuery(User|PublicUser $actor, string $audience): Builder
    {
        $this->assertAudience($actor, $audience);

        return PropertyPublication::query()->where($audience === 'agency' ? 'share_with_agencies' : 'publish_public', true)
            ->whereHas('agency', fn (Builder $query) => $query->where('is_active', true)->where('is_verified', true))
            ->whereHas('property', fn (Builder $query) => $query->where('status', PropertyStatus::Available->value))
            ->with(['property', 'agency', 'images']);
    }

    public function findVisible(int $listingId, User|PublicUser $actor, string $audience): PropertyPublication
    {
        return $this->visibleQuery($actor, $audience)->findOrFail($listingId);
    }
}
