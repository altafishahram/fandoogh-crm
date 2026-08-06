<?php

declare(strict_types=1);

namespace App\Application\Report\Data;

use App\Domain\Shared\Exceptions\DomainConflictException;
use Carbon\CarbonImmutable;

final readonly class ReportPeriod
{
    public CarbonImmutable $fromUtc;

    public CarbonImmutable $toUtc;

    public function __construct(string $from, string $to, string $timezone)
    {
        $localFrom = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $localTo = CarbonImmutable::parse($to, $timezone)->endOfDay();
        if ($localTo->isBefore($localFrom) || $localFrom->diffInDays($localTo) > 366) {
            throw new DomainConflictException('بازه گزارش باید به‌ترتیب و حداکثر ۳۶۶ روز باشد.');
        }

        $this->fromUtc = $localFrom->utc();
        $this->toUtc = $localTo->utc();
    }
}
