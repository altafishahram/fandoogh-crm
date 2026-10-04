<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $phone
 * @property bool $is_active
 * @property CarbonImmutable|null $phone_verified_at
 */
final class PublicUser extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['phone', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'phone_verified_at' => 'immutable_datetime'];
    }
}
