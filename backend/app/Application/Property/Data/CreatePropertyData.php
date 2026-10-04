<?php

declare(strict_types=1);

namespace App\Application\Property\Data;

use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
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
        public ?string $city,
        public ?string $district,
        public string $streetAddress,
        public ?string $postalCode,
        public ?string $latitude,
        public ?string $longitude,
        public ?CarbonImmutable $availableFrom,
        public array $owners,
        public ?EmbeddedOwnerData $embeddedOwner = null,
        public ?PropertyStatus $status = null,
        public ?string $plaque = null,
        public ?int $unitsPerFloor = null,
        public ?int $masterBedrooms = null,
        /** @var list<string>|null */
        public ?array $toiletTypes = null,
        public ?string $cabinetType = null,
        /** @var list<string>|null */
        public ?array $heatingSystems = null,
        /** @var list<string>|null */
        public ?array $coolingSystems = null,
        public ?string $flooringType = null,
        public ?string $renovationStatus = null,
        public ?DeliveryStatus $deliveryStatus = null,
        public ?CarbonImmutable $evacuationDate = null,
        public bool $isConvertible = false,
        public ?string $minimumDeposit = null,
        public ?string $salePricePerSqm = null,
        public string $priceInputMode = 'total',
        public bool $hasMasterBathroom = false,
        public ?string $heatingType = null,
        public ?string $coolingType = null,
        public ?string $buildingOrientation = null,
        public ?string $deedType = null,
        public bool $hasLoan = false,
        public bool $isExchangeable = false,
        public bool $hasPool = false,
        public bool $hasJacuzzi = false,
        public bool $hasSauna = false,
        public ?string $buildingType = null,
        public ?string $structureType = null,
        public bool $hasWater = false,
        public bool $hasElectricity = false,
        public bool $hasGas = false,
        public ?string $telephoneLineCount = null,
        public ?string $landArea = null,
        public ?string $buildingArea = null,
        public bool $canAggregate = false,
        public ?string $landFrontage = null,
        public ?int $provinceId = null,
        public ?int $countyId = null,
        public ?int $cityId = null,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'province_id' => $this->provinceId, 'county_id' => $this->countyId, 'city_id' => $this->cityId,
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
            'plaque' => $this->plaque, 'units_per_floor' => $this->unitsPerFloor,
            'master_bedrooms' => $this->masterBedrooms, 'toilet_types' => $this->toiletTypes,
            'cabinet_type' => $this->cabinetType, 'heating_systems' => $this->heatingSystems,
            'cooling_systems' => $this->coolingSystems, 'flooring_type' => $this->flooringType,
            'renovation_status' => $this->renovationStatus, 'delivery_status' => $this->deliveryStatus,
            'evacuation_date' => $this->evacuationDate, 'is_convertible' => $this->isConvertible,
            'minimum_deposit' => $this->minimumDeposit,
            'has_master_bathroom' => $this->hasMasterBathroom,
            'heating_type' => $this->heatingType, 'cooling_type' => $this->coolingType,
            'building_orientation' => $this->buildingOrientation, 'deed_type' => $this->deedType,
            'has_loan' => $this->hasLoan, 'is_exchangeable' => $this->isExchangeable,
            'has_pool' => $this->hasPool, 'has_jacuzzi' => $this->hasJacuzzi,
            'has_sauna' => $this->hasSauna,
            'building_type' => $this->buildingType,
            'structure_type' => $this->structureType,
            'has_water' => $this->hasWater,
            'has_electricity' => $this->hasElectricity,
            'has_gas' => $this->hasGas,
            'telephone_line_count' => $this->telephoneLineCount,
            'land_area' => $this->landArea,
            'building_area' => $this->buildingArea,
            'can_aggregate' => $this->canAggregate,
            'land_frontage' => $this->landFrontage,
        ];
    }
}
