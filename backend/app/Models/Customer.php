<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Carbon\CarbonInterface;
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
 * @property CarbonInterface|null $matching_eligible_at
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
        'full_name', 'created_by_role', 'desired_property_type', 'rental_deposit_min',
        'rental_deposit_max', 'rental_rent_min', 'rental_rent_max', 'accepts_rent_conversion',
        'toilet_types', 'has_master_bathroom', 'cabinet_type', 'heating_type', 'cooling_type',
        'flooring_type', 'renovation_status', 'building_orientation', 'deed_type', 'has_loan',
        'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna', 'description', 'lock_version',
        'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
        'telephone_line_count', 'land_area', 'building_area',
        'land_area_min', 'land_area_max', 'building_area_min', 'building_area_max',
        'min_parking_spaces', 'has_parking', 'has_storage_room', 'owner_resides',
        'has_elevator', 'has_balcony',
        'matching_eligible_at',
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

    /** @return HasMany<CustomerHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(CustomerHistory::class);
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
            'toilet_types' => 'array',
            'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2',
            'min_area_sqm' => 'decimal:2', 'max_area_sqm' => 'decimal:2',
            'land_area_min' => 'decimal:2', 'land_area_max' => 'decimal:2',
            'building_area_min' => 'decimal:2', 'building_area_max' => 'decimal:2',
            'rental_deposit_min' => 'decimal:2', 'rental_deposit_max' => 'decimal:2',
            'rental_rent_min' => 'decimal:2', 'rental_rent_max' => 'decimal:2',
            'accepts_rent_conversion' => 'boolean', 'has_master_bathroom' => 'boolean',
            'has_loan' => 'boolean', 'is_exchangeable' => 'boolean', 'has_pool' => 'boolean',
            'has_jacuzzi' => 'boolean', 'has_sauna' => 'boolean', 'lock_version' => 'integer',
            'has_water' => 'boolean', 'has_electricity' => 'boolean', 'has_gas' => 'boolean',
            'min_parking_spaces' => 'integer', 'has_parking' => 'boolean',
            'has_storage_room' => 'boolean', 'owner_resides' => 'boolean',
            'has_elevator' => 'boolean', 'has_balcony' => 'boolean',
            'matching_eligible_at' => 'immutable_datetime',
        ];
    }
}
