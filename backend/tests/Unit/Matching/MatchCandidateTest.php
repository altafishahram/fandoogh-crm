<?php

declare(strict_types=1);

namespace Tests\Unit\Matching;

use App\Application\Matching\Data\MatchCandidate;
use App\Domain\Matching\Enums\MatchMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatchCandidateTest extends TestCase
{
    #[DataProvider('selectedRanks')]
    public function test_nullable_ranks_preserve_candidate_scoring_and_fingerprint(?int $propertyRank, ?int $customerRank): void
    {
        $candidate = new MatchCandidate(
            propertyId: 12,
            customerId: 34,
            score: 67.5,
            financialScore: 40.0,
            areaScore: 22.5,
            featureScore: 5.0,
            mode: MatchMode::Direct,
            matchedDepositMin: null,
            matchedDepositMax: null,
            matchedRentMin: null,
            matchedRentMax: null,
            fingerprint: str_repeat('a', 64),
            propertyCreatedTimestamp: 100,
            customerCreatedTimestamp: 200,
        );

        $ranked = $candidate->withRanks($propertyRank, $customerRank);
        $expected = array_replace($candidate->attributes(56), [
            'property_rank' => $propertyRank,
            'customer_rank' => $customerRank,
        ]);

        self::assertSame($expected, $ranked->attributes(56));
        self::assertSame(100, $ranked->propertyCreatedTimestamp);
        self::assertSame(200, $ranked->customerCreatedTimestamp);
        self::assertNull($candidate->propertyRank);
        self::assertNull($candidate->customerRank);
        self::assertNotSame($candidate, $ranked);
    }

    /** @return array<string, array{?int, ?int}> */
    public static function selectedRanks(): array
    {
        return [
            'property only' => [20, null],
            'customer only' => [null, 20],
            'both sides' => [1, 20],
        ];
    }
}
