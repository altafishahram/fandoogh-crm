<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchNotificationRead extends Model
{
    use BelongsToAgency;

    /** @var list<string> */
    protected $fillable = [
        'match_notification_id', 'user_id', 'notification_version', 'read_at',
    ];

    /** @return BelongsTo<MatchNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(MatchNotification::class, 'match_notification_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'notification_version' => 'integer',
            'read_at' => 'immutable_datetime',
        ];
    }
}
