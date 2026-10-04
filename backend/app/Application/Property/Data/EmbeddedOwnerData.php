<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

final readonly class EmbeddedOwnerData
{
    public function __construct(
        public string $fullName,
        public string $mobile,
        public ?string $phone,
        public ?string $notes,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'full_name' => trim($this->fullName),
            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'notes' => $this->notes,
        ];
    }
}
