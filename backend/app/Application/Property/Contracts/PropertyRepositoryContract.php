<?php

declare(strict_types=1);

namespace App\Application\Property\Contracts;

use App\Models\Property;

interface PropertyRepositoryContract
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Property;

    public function save(Property $property): Property;

    public function lock(int $propertyId): Property;
}
