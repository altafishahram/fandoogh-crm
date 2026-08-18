<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use App\Domain\Tenancy\Exceptions\ImmutableTenantException;
use Carbon\CarbonImmutable;
use Database\Factories\PropertyFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $agency_id
 * @property string $code
 * @property string $currency_code
 * @property PropertyType $property_type
 * @property TransactionType $transaction_type
 * @property PropertyStatus $status
 * @property int|null $assigned_agent_id
 * @property int $created_by_user_id
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $archived_at
 */
class Property extends Model
{
    use BelongsToAgency;

    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title', 'description', 'property_type', 'transaction_type', 'status', 'assigned_agent_id',
        'created_by_user_id', 'sale_price', 'deposit_amount', 'monthly_rent', 'area_sqm', 'bedrooms',
        'bathrooms', 'floor_number', 'total_floors', 'year_built', 'parking_spaces', 'has_storage_room',
        'has_elevator', 'has_balcony', 'city', 'district', 'street_address', 'postal_code', 'latitude',
        'longitude', 'available_from', 'closed_at', 'archived_at', 'currency_unit', 'plaque',
        'units_per_floor', 'master_bedrooms', 'toilet_types', 'cabinet_type', 'heating_systems',
        'cooling_systems', 'flooring_type', 'renovation_status', 'delivery_status', 'evacuation_date',
        'is_convertible', 'minimum_deposit', 'created_by_role', 'lock_version',
        'has_master_bathroom', 'heating_type', 'cooling_type', 'building_orientation', 'deed_type',
        'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
        'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
        'telephone_line_count', 'land_area', 'building_area', 'can_aggregate', 'land_frontage',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $property): void {
            if ($property->isDirty('agency_id')) {
                throw new ImmutableTenantException('آژانس ملک قابل تغییر نیست.');
            }

            if ($property->isDirty(['code', 'currency_code'])) {
                throw new DomainException('کد ملک و واحد پول قابل تغییر نیستند.');
            }
        });
    }

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

    /** @return BelongsToMany<Owner, $this> */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'property_owner')
            ->withPivot(['agency_id', 'ownership_percentage', 'is_primary'])
            ->withTimestamps();
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    /** @return HasMany<PropertyNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(PropertyNote::class);
    }

    /** @return HasMany<PropertyHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(PropertyHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'transaction_type' => TransactionType::class,
            'status' => PropertyStatus::class,
            'sale_price' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'monthly_rent' => 'decimal:2',
            'area_sqm' => 'decimal:2', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
            'has_storage_room' => 'boolean', 'has_elevator' => 'boolean', 'has_balcony' => 'boolean',
            'toilet_types' => 'array', 'heating_systems' => 'array', 'cooling_systems' => 'array',
            'delivery_status' => DeliveryStatus::class, 'evacuation_date' => 'immutable_date',
            'is_convertible' => 'boolean', 'minimum_deposit' => 'decimal:2', 'lock_version' => 'integer',
            'has_master_bathroom' => 'boolean', 'has_loan' => 'boolean', 'is_exchangeable' => 'boolean',
            'has_pool' => 'boolean', 'has_jacuzzi' => 'boolean', 'has_sauna' => 'boolean',
            'has_water' => 'boolean', 'has_electricity' => 'boolean', 'has_gas' => 'boolean',
            'can_aggregate' => 'boolean',
            'available_from' => 'immutable_date', 'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
