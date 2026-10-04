<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property OwnerType $owner_type
 * @property string|null $first_name
 * @property string|null $full_name
 * @property string|null $last_name
 * @property string|null $company_name
 * @property string|null $mobile
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $identity_number_encrypted
 * @property int $agency_id
 * @property int|null $province_id
 * @property int|null $county_id
 * @property int|null $city_id
 * @property int $created_by_user_id
 */
class Owner extends Model
{
    use BelongsToAgency;

    /** @use HasFactory<OwnerFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'province_id', 'county_id', 'city_id',
        'owner_type', 'first_name', 'last_name', 'company_name', 'mobile', 'phone', 'email',
        'identity_number_encrypted', 'address_line_1', 'address_line_2', 'city', 'province',
        'postal_code', 'notes', 'created_by_user_id', 'full_name',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsToMany<Property, $this> */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_owner')
            ->withPivot(['agency_id', 'ownership_percentage', 'is_primary'])
            ->withTimestamps();
    }

    /** @return Attribute<?string, ?string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): ?string => self::normalizeEmail($value));
    }

    /** @return Attribute<?string, ?string> */
    protected function mobile(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): ?string => self::normalizePhone($value));
    }

    /** @return Attribute<?string, ?string> */
    protected function phone(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): ?string => self::normalizePhone($value));
    }

    private static function normalizeEmail(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /** @return Attribute<string, string> */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): string {
                $stored = trim((string) $value);
                if ($stored !== '') {
                    return $stored;
                }

                return trim((string) ($this->company_name ?: $this->first_name.' '.$this->last_name));
            },
        );
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
            'owner_type' => OwnerType::class,
            'identity_number_encrypted' => 'encrypted',
        ];
    }
}
