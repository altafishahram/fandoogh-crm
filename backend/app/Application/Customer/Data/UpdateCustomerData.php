<?php

declare(strict_types=1);

namespace App\Application\Customer\Data;

use Carbon\CarbonImmutable;

final readonly class UpdateCustomerData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public array $attributes,
        public CarbonImmutable $expectedUpdatedAt,
        public ?int $expectedVersion = null,
        public ?string $reason = null,
    ) {}
}
