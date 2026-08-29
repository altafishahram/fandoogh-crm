<?php

declare(strict_types=1);

namespace App\Application\Matching\Data;

use App\Domain\Matching\Enums\MatchMode;

final readonly class MatchCandidate
{
    public function __construct(
        public int $propertyId,
        public int $customerId,
        public float $score,
        public float $financialScore,
        public float $areaScore,
        public float $featureScore,
        public MatchMode $mode,
        public ?float $matchedDepositMin,
        public ?float $matchedDepositMax,
        public ?float $matchedRentMin,
        public ?float $matchedRentMax,
        public string $fingerprint,
        public int $propertyCreatedTimestamp,
        public int $customerCreatedTimestamp,
        public ?int $propertyRank = null,
        public ?int $customerRank = null,
    ) {}

    public function withRanks(int $propertyRank, int $customerRank): self
    {
        return new self(
            $this->propertyId,
            $this->customerId,
            $this->score,
            $this->financialScore,
            $this->areaScore,
            $this->featureScore,
            $this->mode,
            $this->matchedDepositMin,
            $this->matchedDepositMax,
            $this->matchedRentMin,
            $this->matchedRentMax,
            $this->fingerprint,
            $this->propertyCreatedTimestamp,
            $this->customerCreatedTimestamp,
            $propertyRank,
            $customerRank,
        );
    }

    /** @return array<string, mixed> */
    public function attributes(int $agencyId): array
    {
        return [
            'agency_id' => $agencyId,
            'property_id' => $this->propertyId,
            'customer_id' => $this->customerId,
            'score' => round($this->score, 2),
            'financial_score' => round($this->financialScore, 2),
            'area_score' => round($this->areaScore, 2),
            'feature_score' => round($this->featureScore, 2),
            'match_mode' => $this->mode,
            'property_rank' => $this->propertyRank,
            'customer_rank' => $this->customerRank,
            'matched_deposit_min' => $this->matchedDepositMin,
            'matched_deposit_max' => $this->matchedDepositMax,
            'matched_rent_min' => $this->matchedRentMin,
            'matched_rent_max' => $this->matchedRentMax,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
