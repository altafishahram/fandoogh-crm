<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\SavedFilter\Enums\FilterModule;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $agency_id
 * @property int $user_id
 * @property FilterModule $module
 * @property string $name
 * @property array<string, mixed> $filters
 * @property string|null $sort
 * @property bool $is_default
 */
class SavedFilter extends Model
{
    use BelongsToAgency;

    /** @var list<string> */
    protected $fillable = ['user_id', 'module', 'name', 'filters', 'sort', 'is_default'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'module' => FilterModule::class,
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
