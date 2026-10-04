<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Customer\Services\CreateCustomerService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CreatesCustomer
{
    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return app(CreateCustomerService::class)->execute($actor, PanelDataMapper::customerCreate($data));
    }
}
