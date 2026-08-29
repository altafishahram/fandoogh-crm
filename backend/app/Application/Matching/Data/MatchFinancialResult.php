<?php

declare(strict_types=1);

namespace App\Application\Matching\Data;

use App\Domain\Matching\Enums\MatchMode;

final readonly class MatchFinancialResult
{
    public function __construct(
        public float $score,
        public MatchMode $mode,
        public ?float $depositMin = null,
        public ?float $depositMax = null,
        public ?float $rentMin = null,
        public ?float $rentMax = null,
    ) {}
}
