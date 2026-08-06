<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Owner\Contracts\OwnerRepositoryContract;
use App\Models\Owner;

final class EloquentOwnerRepository implements OwnerRepositoryContract
{
    public function create(array $attributes): Owner
    {
        return Owner::query()->create($attributes);
    }

    public function save(Owner $owner): Owner
    {
        $owner->save();

        return $owner->refresh();
    }
}
