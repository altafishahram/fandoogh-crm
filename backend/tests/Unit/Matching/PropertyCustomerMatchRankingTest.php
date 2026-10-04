<?php

declare(strict_types=1);

namespace Tests\Unit\Matching;

use App\Application\Matching\Data\MatchCandidate;
use App\Application\Matching\Services\PropertyCustomerMatchingService;
use App\Domain\Matching\Enums\MatchMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class PropertyCustomerMatchRankingTest extends TestCase
{
    #[DataProvider('rankingSides')]
    public function test_each_side_ranks_twenty_by_score_then_recency_then_id(bool $byProperty): void
    {
        $candidates = [];
        for ($id = 1; $id <= 25; $id++) {
            $timestamp = match ($id) {
                1 => 50,
                2, 3 => 200,
                default => 100,
            };
            $candidates[] = new MatchCandidate(
                propertyId: $byProperty ? 1 : $id,
                customerId: $byProperty ? $id : 1,
                score: $id === 1 ? 100.0 : 45.0,
                financialScore: 30.0,
                areaScore: 10.0,
                featureScore: 5.0,
                mode: MatchMode::Direct,
                matchedDepositMin: null,
                matchedDepositMax: null,
                matchedRentMin: null,
                matchedRentMax: null,
                fingerprint: str_repeat('a', 64),
                propertyCreatedTimestamp: $byProperty ? 0 : $timestamp,
                customerCreatedTimestamp: $byProperty ? $timestamp : 0,
            );
        }
        $service = (new ReflectionClass(PropertyCustomerMatchingService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PropertyCustomerMatchingService::class, $byProperty ? 'rankByProperty' : 'rankByCustomer');
        $ranks = $method->invoke($service, $candidates);
        $selectedIds = [1, 3, 2, ...range(25, 9)];
        $expectedKeys = array_map(
            static fn (int $id): string => $byProperty ? '1:'.$id : $id.':1',
            $selectedIds,
        );

        self::assertSame($expectedKeys, array_keys($ranks));
        self::assertSame(range(1, 20), array_values($ranks));
    }

    /** @return array<string, array{bool}> */
    public static function rankingSides(): array
    {
        return [
            'property side' => [true],
            'customer side' => [false],
        ];
    }
}
