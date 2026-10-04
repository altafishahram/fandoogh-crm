<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\AgencyScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $agency_id
 * @property int $property_id
 * @property int $responding_user_id
 * @property int $version
 * @property bool $publish_public
 * @property bool $share_with_agencies
 * @property string $public_title
 * @property string|null $public_description
 * @property CarbonImmutable|null $published_at
 * @property Property|null $property
 * @property Agency $agency
 * @property Collection<int, PropertyImage> $images
 */
final class PropertyPublication extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withoutGlobalScope(AgencyScope::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsToMany<PropertyImage, $this> */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(PropertyImage::class, 'publication_images', 'publication_id', 'property_image_id')->withoutGlobalScope(AgencyScope::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    protected function casts(): array
    {
        return ['share_with_agencies' => 'boolean', 'publish_public' => 'boolean', 'version' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
