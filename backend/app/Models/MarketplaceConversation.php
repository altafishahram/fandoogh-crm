<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MarketplaceConversation extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<PropertyPublication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(PropertyPublication::class, 'property_publication_id');
    }

    /** @return HasMany<MarketplaceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(MarketplaceMessage::class);
    }
}
