<?php

declare(strict_types=1);

namespace App\Application\Matching\Services;

use App\Jobs\RebuildAgencyPropertyCustomerMatches;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class MatchingRebuildDispatcher
{
    public function dispatchForAgency(int $agencyId): void
    {
        $dispatch = static function () use ($agencyId): void {
            try {
                RebuildAgencyPropertyCustomerMatches::dispatch($agencyId);
            } catch (Throwable $exception) {
                report($exception);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);

            return;
        }

        $dispatch();
    }
}
