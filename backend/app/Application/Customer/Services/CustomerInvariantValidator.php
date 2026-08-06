<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;

final class CustomerInvariantValidator
{
    /** @param array<string, mixed> $attributes */
    public function validate(array $attributes): void
    {
        $types = $attributes['preferred_property_types'] ?? null;
        if ($types !== null) {
            if (! is_array($types) || count($types) !== count(array_unique($types))) {
                throw new DomainConflictException('انواع ملک ترجیحی باید یکتا باشند.');
            }
            foreach ($types as $type) {
                if (! is_string($type) || ! in_array($type, PropertyType::values(), true)) {
                    throw new DomainConflictException('یکی از انواع ملک ترجیحی پشتیبانی نمی‌شود.');
                }
            }
        }

        $this->orderedPair($attributes['budget_min'] ?? null, $attributes['budget_max'] ?? null, true);
        $this->orderedPair($attributes['min_area_sqm'] ?? null, $attributes['max_area_sqm'] ?? null, false);
    }

    public function conversion(Property $property, CustomerIntent $intent, User $actor): void
    {
        if ($property->transaction_type !== $intent->transactionType()) {
            throw new DomainConflictException('ملک نهایی‌شده با نوع درخواست مشتری سازگار نیست.');
        }

        if ($actor->roleName() === RoleName::Agent && $property->assigned_agent_id !== $actor->getKey()) {
            throw new DomainConflictException('این کارشناس به ملک نهایی‌شده دسترسی ندارد.');
        }
    }

    private function orderedPair(mixed $minimum, mixed $maximum, bool $allowZero): void
    {
        $minimumValue = $minimum === null ? null : (float) $minimum;
        $maximumValue = $maximum === null ? null : (float) $maximum;
        if (($minimumValue !== null && ($allowZero ? $minimumValue < 0 : $minimumValue <= 0))
            || ($maximumValue !== null && ($allowZero ? $maximumValue < 0 : $maximumValue <= 0))
            || ($minimumValue !== null && $maximumValue !== null && $minimumValue > $maximumValue)) {
            throw new DomainConflictException('بازه عددی واردشده معتبر نیست.');
        }
    }
}
