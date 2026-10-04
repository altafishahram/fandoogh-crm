<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgencyFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int|null $province_id
 * @property int|null $county_id
 * @property int|null $city_id
 * @property bool $is_verified
 * @property bool $is_active
 */
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'province_id', 'county_id', 'city_id',
        'slug',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country_code',
        'timezone',
        'locale',
        'currency_code',
        'is_active',
        'activated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $agency): void {
            if ((bool) $agency->getOriginal('is_active') && $agency->isDirty('slug')) {
                throw new DomainException('شناسه انگلیسی آژانس فعال قابل تغییر نیست.');
            }

            if ($agency->isDirty('is_active') && $agency->is_active && $agency->activated_at === null) {
                $agency->setAttribute('activated_at', now());
            }
        });
    }

    /** @return HasOne<AgencySettings, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(AgencySettings::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Owner, $this> */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_verified' => 'boolean', 'verified_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }
}
