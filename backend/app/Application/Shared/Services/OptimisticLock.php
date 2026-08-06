<?php

declare(strict_types=1);

namespace App\Application\Shared\Services;

use App\Domain\Shared\Exceptions\StaleRecordException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class OptimisticLock
{
    public function assertCurrent(Model $model, CarbonImmutable $expected): void
    {
        $updatedAt = $model->getAttribute('updated_at');

        if (! $updatedAt instanceof CarbonInterface || ! $updatedAt->equalTo($expected)) {
            throw new StaleRecordException('این رکورد پس از بارگذاری تغییر کرده است.', [
                'id' => $model->getKey(),
                'updated_at' => $updatedAt instanceof CarbonInterface ? $updatedAt->toISOString() : null,
            ]);
        }
    }
}
