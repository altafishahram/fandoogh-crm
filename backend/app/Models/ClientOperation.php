<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

final class ClientOperation extends Model
{
    use BelongsToAgency;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'operation_key', 'request_hash', 'resource_type', 'resource_id',
        'response_status', 'response_body',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
        ];
    }
}
