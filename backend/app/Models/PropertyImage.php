<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $agency_id
 * @property int $property_id
 * @property int $uploaded_by_user_id
 * @property string $storage_path
 * @property string $mime_type
 * @property int $sort_order
 * @property bool $is_cover
 */
class PropertyImage extends Model
{
    use BelongsToAgency;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'uploaded_by_user_id', 'storage_path', 'original_name', 'mime_type',
        'size_bytes', 'width', 'height', 'sort_order', 'is_cover',
    ];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }
}
