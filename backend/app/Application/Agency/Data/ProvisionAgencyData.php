<?php

declare(strict_types=1);

namespace App\Application\Agency\Data;

final readonly class ProvisionAgencyData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $email,
        public string $phone,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $province,
        public ?string $postalCode,
        public string $countryCode,
        public string $timezone,
        public string $locale,
        public string $currencyCode,
        public string $propertyCodePrefix,
        public int $defaultPageSize = 25,
    ) {}
}
