<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class AgencySettings extends Model
{
    use BelongsToAgency;

    public $incrementing = false;

    protected $primaryKey = 'agency_id';

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'property_code_prefix',
        'next_property_sequence',
        'default_page_size',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_property_sequence' => 'integer',
            'default_page_size' => 'integer',
        ];
    }
}
