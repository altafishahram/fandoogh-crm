<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketplaceMessage extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['image_path'];

    /** @return BelongsTo<MarketplaceConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConversation::class, 'marketplace_conversation_id');
    }
}
