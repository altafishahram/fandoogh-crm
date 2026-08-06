<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\User;

final class PropertyHistoryWriter
{
    /** @param array<string, mixed>|null $changedFields */
    public function write(
        Property $property,
        User $actor,
        PropertyHistoryAction $action,
        ?PropertyStatus $fromStatus = null,
        ?PropertyStatus $toStatus = null,
        ?array $changedFields = null,
        ?string $reason = null,
    ): PropertyHistory {
        return PropertyHistory::query()->create([
            'property_id' => $property->getKey(),
            'changed_by_user_id' => $actor->getKey(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_fields' => $changedFields,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
