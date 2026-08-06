<?php

declare(strict_types=1);

namespace App\Application\Customer\Data;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;

final readonly class CreateCustomerData
{
    /** @param list<string>|null $preferredPropertyTypes */
    public function __construct(
        public int $assignedAgentId,
        public string $firstName,
        public string $lastName,
        public string $mobile,
        public ?string $phone,
        public ?string $email,
        public PreferredContactMethod $preferredContactMethod,
        public CustomerIntent $intent,
        public ?array $preferredPropertyTypes,
        public ?string $budgetMin,
        public ?string $budgetMax,
        public ?string $desiredCity,
        public ?string $desiredDistrict,
        public ?string $minAreaSqm,
        public ?string $maxAreaSqm,
        public ?int $minBedrooms,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'first_name' => $this->firstName, 'last_name' => $this->lastName,
            'mobile' => $this->mobile, 'phone' => $this->phone, 'email' => $this->email,
            'preferred_contact_method' => $this->preferredContactMethod, 'intent' => $this->intent,
            'preferred_property_types' => $this->preferredPropertyTypes,
            'budget_min' => $this->budgetMin, 'budget_max' => $this->budgetMax,
            'desired_city' => $this->desiredCity, 'desired_district' => $this->desiredDistrict,
            'min_area_sqm' => $this->minAreaSqm, 'max_area_sqm' => $this->maxAreaSqm,
            'min_bedrooms' => $this->minBedrooms,
        ];
    }
}
