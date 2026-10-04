<?php

declare(strict_types=1);

namespace Tests\Unit\Matching;

use App\Jobs\RebuildAgencyPropertyCustomerMatches;
use PHPUnit\Framework\TestCase;

final class RebuildAgencyPropertyCustomerMatchesTest extends TestCase
{
    public function test_job_declares_retry_policy(): void
    {
        $job = new RebuildAgencyPropertyCustomerMatches(42);

        self::assertSame(42, $job->agencyId);
        self::assertSame(3, $job->tries);
        self::assertSame([10, 60, 180], $job->backoff);
        self::assertSame(120, $job->timeout);
    }
}
