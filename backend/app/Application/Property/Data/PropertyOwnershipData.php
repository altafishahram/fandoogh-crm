<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

final readonly class PropertyOwnershipData
{
    public function __construct(
        public int $ownerId,
        public ?string $ownershipPercentage,
        public bool $isPrimary,
    ) {}
}
