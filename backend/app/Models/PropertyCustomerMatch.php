<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Matching\Enums\MatchMode;
use App\Domain\Tenancy\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyCustomerMatch extends Model
{
    use BelongsToAgency;

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'customer_id', 'score', 'financial_score', 'area_score', 'feature_score',
        'match_mode', 'property_rank', 'customer_rank', 'matched_deposit_min',
        'matched_deposit_max', 'matched_rent_min', 'matched_rent_max', 'fingerprint',
    ];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'financial_score' => 'decimal:2',
            'area_score' => 'decimal:2',
            'feature_score' => 'decimal:2',
            'match_mode' => MatchMode::class,
            'property_rank' => 'integer',
            'customer_rank' => 'integer',
            'matched_deposit_min' => 'decimal:2',
            'matched_deposit_max' => 'decimal:2',
            'matched_rent_min' => 'decimal:2',
            'matched_rent_max' => 'decimal:2',
        ];
    }
}
