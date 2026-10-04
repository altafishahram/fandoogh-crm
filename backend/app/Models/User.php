<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Exceptions\ImmutableTenantException;
use App\Domain\User\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

/** @property Agency|null $agency */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'must_change_password',
        'last_login_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('agency_id')) {
                throw new ImmutableTenantException('آژانس کاربر قابل تغییر نیست.');
            }
        });

        static::updated(function (self $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        static::softDeleted(fn (self $user) => $user->tokens()->delete());
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<Property, $this> */
    public function assignedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'assigned_agent_id');
    }

    /** @return HasMany<Customer, $this> */
    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_agent_id');
    }

    /** @return HasMany<SavedFilter, $this> */
    public function savedFilters(): HasMany
    {
        return $this->hasMany(SavedFilter::class);
    }

    public function roleName(): RoleName
    {
        $roleNames = $this->getRoleNames();

        if ($roleNames->count() !== 1) {
            throw new LogicException('هر کاربر باید دقیقاً یک نقش داشته باشد.');
        }

        $role = RoleName::tryFrom((string) $roleNames->first());

        if ($role === null) {
            throw new LogicException('نقش اختصاص‌یافته به کاربر پشتیبانی نمی‌شود.');
        }

        return $role;
    }

    public function replaceRole(RoleName $role): void
    {
        $this->syncRoles([$role->value]);
        $this->tokens()->delete();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active || $this->trashed()) {
            return false;
        }

        $role = $this->roleName();
        $roleMatches = match ($panel->getId()) {
            'control' => $role === RoleName::SuperAdmin,
            'agency' => $role === RoleName::AgencyManager,
            'agent' => $role === RoleName::Agent,
            default => false,
        };

        if (! $roleMatches) {
            return false;
        }

        return $role === RoleName::SuperAdmin
            || ($this->agency !== null && $this->agency->is_active);
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
