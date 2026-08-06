<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

use Carbon\CarbonImmutable;

final readonly class UpdatePropertyData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public array $attributes,
        public CarbonImmutable $expectedUpdatedAt,
    ) {}
}
