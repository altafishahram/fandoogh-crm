<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Matching\Services\PropertyCustomerMatchingService;
use App\Models\Agency;
use Illuminate\Console\Command;

/** Explicit post-release backfill of existing eligible records, never legacy enrollment. */
final class RebuildMatches extends Command
{
    protected $signature = 'matching:rebuild {--agency=* : شناسهٔ آژانس؛ در صورت نبودن، همهٔ آژانس‌های فعال}';

    protected $description = 'بازسازی تطبیق‌های رکوردهای واجد شرایط در آژانس‌های فعال';

    public function handle(PropertyCustomerMatchingService $matching): int
    {
        $ids = $this->option('agency');
        if (! is_array($ids) || count(array_filter($ids, static fn (mixed $id): bool => ! is_string($id) || ! ctype_digit($id) || (int) $id < 1)) > 0) {
            $this->error('شناسهٔ آژانس باید عدد صحیح مثبت باشد.');

            return self::INVALID;
        }
        $agencies = Agency::query()->where('is_active', true)->orderBy('id');
        if ($ids !== []) {
            $agencies->whereIn('id', $ids);
        }
        $completed = 0;
        foreach ($agencies->lazyById(100) as $agency) {
            $matching->rebuildForAgency((int) $agency->getKey());
            $completed++;
            $this->line('تطبیق آژانس '.$agency->getKey().' بازسازی شد.');
        }
        $this->info('بازسازی '.$completed.' آژانس کامل شد؛ اطلاعات قدیمیِ فاقد شرایط وارد تطبیق نشدند.');

        return self::SUCCESS;
    }
}
