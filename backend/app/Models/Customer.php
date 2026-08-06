<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $agency_id
 * @property int $assigned_agent_id
 * @property int $created_by_user_id
 * @property int|null $converted_property_id
 * @property CustomerIntent $intent
 * @property CustomerStatus $status
 * @property PreferredContactMethod $preferred_contact_method
 * @property User|null $assignedAgent
 */
class Customer extends Model
{
    use BelongsToAgency;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'assigned_agent_id', 'created_by_user_id', 'first_name', 'last_name', 'mobile', 'phone',
        'email', 'preferred_contact_method', 'intent', 'status', 'preferred_property_types',
        'budget_min', 'budget_max', 'desired_city', 'desired_district', 'min_area_sqm',
        'max_area_sqm', 'min_bedrooms', 'converted_property_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<Property, $this> */
    public function convertedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'converted_property_id');
    }

    /** @return HasMany<CustomerNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /** @return Attribute<string, string> */
    protected function mobile(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): string => self::normalizePhone($value) ?? '');
    }

    /** @return Attribute<?string, ?string> */
    protected function phone(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): ?string => self::normalizePhone($value));
    }

    /** @return Attribute<?string, ?string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: static function (mixed $value): ?string {
            $normalized = mb_strtolower(trim((string) $value));

            return $normalized === '' ? null : $normalized;
        });
    }

    private static function normalizePhone(mixed $value): ?string
    {
        $normalized = preg_replace('/(?!^\+)\D+/', '', trim((string) $value));

        return $normalized === null || $normalized === '' ? null : $normalized;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preferred_contact_method' => PreferredContactMethod::class,
            'intent' => CustomerIntent::class,
            'status' => CustomerStatus::class,
            'preferred_property_types' => 'array',
            'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2',
            'min_area_sqm' => 'decimal:2', 'max_area_sqm' => 'decimal:2',
        ];
    }
}
