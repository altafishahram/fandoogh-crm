<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PropertyImageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'original_name' => $this->resource->original_name,
            'mime_type' => $this->resource->mime_type,
            'size_bytes' => $this->resource->size_bytes,
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'sort_order' => $this->resource->sort_order,
            'is_cover' => $this->resource->is_cover,
            'content_url' => route('api.v1.properties.images.content', [
                'property' => $this->resource->property_id,
                'image' => $this->resource->getKey(),
            ]),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
