<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\SavedFilter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SavedFilterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'module' => $this->resource->module->value,
            'name' => $this->resource->name,
            'filters' => $this->resource->filters,
            'sort' => $this->resource->sort,
            'is_default' => $this->resource->is_default,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
