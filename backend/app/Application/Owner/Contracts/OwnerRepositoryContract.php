<?php

declare(strict_types=1);

namespace App\Application\Owner\Contracts;

use App\Models\Owner;

interface OwnerRepositoryContract
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Owner;

    public function save(Owner $owner): Owner;
}
