<?php

declare(strict_types=1);

namespace App\Filament\Shared\Concerns;

use App\Application\Customer\Services\UpdateCustomerService;
use App\Filament\Shared\Support\PanelDataMapper;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

trait UpdatesCustomer
{
    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof Customer, 401);

        return app(UpdateCustomerService::class)->execute(
            $actor, $record,
            PanelDataMapper::customerUpdate($data, CarbonImmutable::parse((string) $record->updated_at)),
        );
    }
}
