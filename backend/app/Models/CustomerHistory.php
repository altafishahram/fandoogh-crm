<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Customer\Enums\CustomerHistoryAction;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CustomerHistory extends Model
{
    use BelongsToAgency;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'changed_by_user_id', 'action', 'from_status', 'to_status',
        'changed_fields', 'reason', 'occurred_at',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('تاریخچه مشتری قابل ویرایش نیست.');
        });
        self::deleting(static function (): never {
            throw new LogicException('تاریخچه مشتری قابل حذف نیست.');
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
            'action' => CustomerHistoryAction::class,
            'from_status' => CustomerStatus::class,
            'to_status' => CustomerStatus::class,
            'changed_fields' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
