<?php

declare(strict_types=1);

namespace App\Application\Owner\Services;

use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;

final class DeleteOwnerService
{
    public function execute(Owner $owner): void
    {
        if ($owner->properties()->exists()) {
            throw new DomainConflictException('مالک مرتبط با یک ملک فعال قابل حذف نیست.');
        }

        $owner->delete();
    }
}
