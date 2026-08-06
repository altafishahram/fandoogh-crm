<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use Carbon\CarbonImmutable;

final readonly class CreatePropertyData
{
    /** @param list<PropertyOwnershipData> $owners */
    public function __construct(
        public string $title,
        public ?string $description,
        public PropertyType $propertyType,
        public TransactionType $transactionType,
        public ?int $assignedAgentId,
        public ?string $salePrice,
        public ?string $depositAmount,
        public ?string $monthlyRent,
        public ?string $areaSqm,
        public ?int $bedrooms,
        public ?int $bathrooms,
        public ?int $floorNumber,
        public ?int $totalFloors,
        public ?int $yearBuilt,
        public int $parkingSpaces,
        public bool $hasStorageRoom,
        public bool $hasElevator,
        public bool $hasBalcony,
        public string $city,
        public ?string $district,
        public string $streetAddress,
        public ?string $postalCode,
        public ?string $latitude,
        public ?string $longitude,
        public ?CarbonImmutable $availableFrom,
        public array $owners,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'title' => $this->title, 'description' => $this->description,
            'property_type' => $this->propertyType, 'transaction_type' => $this->transactionType,
            'sale_price' => $this->salePrice, 'deposit_amount' => $this->depositAmount,
            'monthly_rent' => $this->monthlyRent, 'area_sqm' => $this->areaSqm,
            'bedrooms' => $this->bedrooms, 'bathrooms' => $this->bathrooms,
            'floor_number' => $this->floorNumber, 'total_floors' => $this->totalFloors,
            'year_built' => $this->yearBuilt, 'parking_spaces' => $this->parkingSpaces,
            'has_storage_room' => $this->hasStorageRoom, 'has_elevator' => $this->hasElevator,
            'has_balcony' => $this->hasBalcony, 'city' => $this->city, 'district' => $this->district,
            'street_address' => $this->streetAddress, 'postal_code' => $this->postalCode,
            'latitude' => $this->latitude, 'longitude' => $this->longitude,
            'available_from' => $this->availableFrom,
        ];
    }
}
