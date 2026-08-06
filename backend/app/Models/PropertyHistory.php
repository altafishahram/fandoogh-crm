<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PropertyHistory extends Model
{
    use BelongsToAgency;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'changed_by_user_id', 'action', 'from_status', 'to_status',
        'changed_fields', 'reason', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('تاریخچه ملک قابل ویرایش نیست.');
        });
        static::deleting(static function (): never {
            throw new LogicException('تاریخچه ملک قابل حذف نیست.');
        });
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => PropertyHistoryAction::class,
            'from_status' => PropertyStatus::class,
            'to_status' => PropertyStatus::class,
            'changed_fields' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
