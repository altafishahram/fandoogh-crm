<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeletePropertyService
{
    public function __construct(
        private PropertyHistoryWriter $history,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(User $actor, Property $property): void
    {
        DB::transaction(function () use ($actor, $property): void {
            $property->delete();
            PropertyPublication::query()->where('property_id', $property->getKey())
                ->update(['share_with_agencies' => false, 'publish_public' => false, 'version' => DB::raw('version + 1')]);
            $this->history->write($property, $actor, PropertyHistoryAction::Deleted);
        });

        $this->matching->dispatchForAgency((int) $property->agency_id);
    }
}
