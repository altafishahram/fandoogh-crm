<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchNotification extends Model
{
    use BelongsToAgency;

    /** @var list<string> */
    protected $fillable = [
        'property_customer_match_id', 'version', 'title', 'body',
    ];

    /** @return BelongsTo<PropertyCustomerMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(PropertyCustomerMatch::class, 'property_customer_match_id');
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<MatchNotificationRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(MatchNotificationRead::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
