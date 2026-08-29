<?php

declare(strict_types=1);

namespace App\Application\Customer\Data;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\PreferredContactMethod;

final readonly class CreateCustomerData
{
    /**
     * @param  list<string>|null  $preferredPropertyTypes
     * @param  list<string>|null  $toiletTypes
     */
    public function __construct(
        public ?int $assignedAgentId,
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
        public ?string $fullName = null,
        public ?string $desiredPropertyType = null,
        public ?string $rentalDepositMin = null,
        public ?string $rentalDepositMax = null,
        public ?string $rentalRentMin = null,
        public ?string $rentalRentMax = null,
        public bool $acceptsRentConversion = true,
        public ?string $description = null,
        public ?array $toiletTypes = null,
        public bool $hasMasterBathroom = false,
        public ?string $cabinetType = null,
        public ?string $heatingType = null,
        public ?string $coolingType = null,
        public ?string $flooringType = null,
        public ?string $renovationStatus = null,
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
        public ?int $minParkingSpaces = null,
        public bool $hasElevator = false,
        public bool $hasBalcony = false,
        public bool $hasParking = false,
        public bool $hasStorageRoom = false,
        public bool $ownerResides = false,
        public ?string $landAreaMin = null,
        public ?string $landAreaMax = null,
        public ?string $buildingAreaMin = null,
        public ?string $buildingAreaMax = null,
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
            'full_name' => $this->fullName,
            'desired_property_type' => $this->desiredPropertyType,
            'rental_deposit_min' => $this->rentalDepositMin,
            'rental_deposit_max' => $this->rentalDepositMax,
            'rental_rent_min' => $this->rentalRentMin,
            'rental_rent_max' => $this->rentalRentMax,
            'accepts_rent_conversion' => $this->acceptsRentConversion,
            'toilet_types' => $this->toiletTypes,
            'has_master_bathroom' => $this->hasMasterBathroom,
            'cabinet_type' => $this->cabinetType,
            'heating_type' => $this->heatingType,
            'cooling_type' => $this->coolingType,
            'flooring_type' => $this->flooringType,
            'renovation_status' => $this->renovationStatus,
            'building_orientation' => $this->buildingOrientation,
            'deed_type' => $this->deedType,
            'has_loan' => $this->hasLoan,
            'is_exchangeable' => $this->isExchangeable,
            'has_pool' => $this->hasPool,
            'has_jacuzzi' => $this->hasJacuzzi,
            'has_sauna' => $this->hasSauna,
            'building_type' => $this->buildingType,
            'structure_type' => $this->structureType,
            'has_water' => $this->hasWater,
            'has_electricity' => $this->hasElectricity,
            'has_gas' => $this->hasGas,
            'telephone_line_count' => $this->telephoneLineCount,
            'land_area' => $this->landArea,
            'building_area' => $this->buildingArea,
            'min_parking_spaces' => $this->minParkingSpaces,
            'has_parking' => $this->hasParking,
            'has_storage_room' => $this->hasStorageRoom,
            'owner_resides' => $this->ownerResides,
            'land_area_min' => $this->landAreaMin,
            'land_area_max' => $this->landAreaMax,
            'building_area_min' => $this->buildingAreaMin,
            'building_area_max' => $this->buildingAreaMax,
            'has_elevator' => $this->hasElevator,
            'has_balcony' => $this->hasBalcony,
            'description' => $this->description,
        ];
    }
}
