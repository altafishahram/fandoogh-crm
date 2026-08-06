<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeletePropertyService
{
    public function __construct(private PropertyHistoryWriter $history) {}

    public function execute(User $actor, Property $property): void
    {
        DB::transaction(function () use ($actor, $property): void {
            $property->delete();
            $this->history->write($property, $actor, PropertyHistoryAction::Deleted);
        });
    }
}
