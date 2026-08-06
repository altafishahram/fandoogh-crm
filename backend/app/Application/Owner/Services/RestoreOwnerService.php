<?php

declare(strict_types=1);

namespace App\Application\Owner\Services;

use App\Models\Owner;

final class RestoreOwnerService
{
    public function execute(Owner $owner): Owner
    {
        $owner->restore();

        return $owner->refresh();
    }
}
