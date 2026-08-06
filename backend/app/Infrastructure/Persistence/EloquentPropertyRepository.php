<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Models\Property;

final class EloquentPropertyRepository implements PropertyRepositoryContract
{
    public function create(array $attributes): Property
    {
        $property = new Property;
        $property->forceFill($attributes);
        $property->save();

        return $property;
    }

    public function save(Property $property): Property
    {
        $property->save();

        return $property->refresh();
    }

    public function lock(int $propertyId): Property
    {
        return Property::query()->lockForUpdate()->findOrFail($propertyId);
    }
}
