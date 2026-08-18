<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

use App\Domain\Property\Enums\PropertyStatus;
use Carbon\CarbonImmutable;

final readonly class ChangePropertyStatusData
{
    public function __construct(
        public PropertyStatus $status,
        public ?string $reason,
        public ?CarbonImmutable $closedAt,
        public CarbonImmutable $expectedUpdatedAt,
        public ?int $expectedVersion = null,
    ) {}
}
