<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Domain\Customer\Enums\CustomerHistoryAction;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerHistory;
use App\Models\User;

final class CustomerHistoryWriter
{
    /** @param array<string, mixed>|null $changedFields */
    public function write(
        Customer $customer,
        User $actor,
        CustomerHistoryAction $action,
        ?CustomerStatus $fromStatus = null,
        ?CustomerStatus $toStatus = null,
        ?array $changedFields = null,
        ?string $reason = null,
    ): CustomerHistory {
        return CustomerHistory::query()->create([
            'customer_id' => $customer->getKey(),
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
