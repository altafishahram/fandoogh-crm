<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OwnerSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'full_name' => $this->resource->full_name,
            'owner_type' => $this->resource->owner_type->value,
            'display_name' => $this->resource->owner_type->value === 'company'
                ? $this->resource->company_name
                : trim($this->resource->first_name.' '.$this->resource->last_name),
            'mobile' => $this->resource->mobile,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
