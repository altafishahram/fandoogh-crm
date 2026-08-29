<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Matching\Services\PropertyCustomerMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RebuildAgencyPropertyCustomerMatches implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public int $timeout = 120;

    public function __construct(public readonly int $agencyId) {}

    public function handle(PropertyCustomerMatchingService $matching): void
    {
        $matching->rebuildForAgency($this->agencyId);
    }
}
