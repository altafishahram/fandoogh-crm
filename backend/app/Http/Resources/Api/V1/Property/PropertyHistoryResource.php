<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PropertyHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'changed_by_user_id' => $this->resource->changed_by_user_id,
            'action' => $this->resource->action->value,
            'from_status' => $this->resource->from_status?->value,
            'to_status' => $this->resource->to_status?->value,
            'changed_fields' => $this->resource->changed_fields,
            'reason' => $this->resource->reason,
            'occurred_at' => $this->resource->occurred_at->toISOString(),
        ];
    }
}
