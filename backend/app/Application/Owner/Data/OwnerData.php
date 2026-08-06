<?php

declare(strict_types=1);

namespace App\Application\Owner\Data;

use App\Domain\Owner\Enums\OwnerType;

final readonly class OwnerData
{
    public function __construct(
        public OwnerType $ownerType,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $companyName,
        public ?string $mobile,
        public ?string $phone,
        public ?string $email,
        public ?string $identityNumber,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $province,
        public ?string $postalCode,
        public ?string $notes,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'owner_type' => $this->ownerType,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company_name' => $this->companyName,
            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'email' => $this->email,
            'identity_number_encrypted' => $this->identityNumber,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'notes' => $this->notes,
        ];
    }
}
