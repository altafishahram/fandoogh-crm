<?php

declare(strict_types=1);

namespace App\Application\SavedFilter\Data;

use App\Domain\SavedFilter\Enums\FilterModule;

final readonly class SavedFilterData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public FilterModule $module,
        public string $name,
        public array $filters,
        public ?string $sort,
        public bool $isDefault,
    ) {}
}
