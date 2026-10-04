<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\MatchNotifications\MatchNotificationResource as AgencyNotificationResource;
use App\Filament\Agent\Resources\MatchNotifications\MatchNotificationResource as AgentNotificationResource;
use App\Filament\Shared\Resources\MatchNotifications\MatchNotificationResourceBase;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class RelatedMatchList extends Component
{
    #[Locked]
    public string $side;

    #[Locked]
    public int $recordId;

    #[Locked]
    public int $visibleCount = 10;

    public function mount(string $side, int $recordId): void
    {
        abort_unless(in_array($side, ['property', 'customer'], true), 404);
        $this->side = $side;
        $this->recordId = $recordId;
        $this->authorizedRecord();
    }

    public function loadMore(): void
    {
        $this->authorizedRecord();
        $this->visibleCount = min(20, $this->visibleCount + 10);
    }

    public function render(): View
    {
        $record = $this->authorizedRecord();
        $actor = $this->actor();
        $service = app(RelatedMatchQuery::class);
        $service->loadSummary($record, $actor);
        $query = $record instanceof Property
            ? $service->forProperty($record, $actor)
            : $service->forCustomer($record, $actor);
        $notifications = $service->withReadState($query, $actor)
            ->with(['match.property', 'match.customer'])
            ->limit(min(20, $this->visibleCount))
            ->get();
        $panel = $actor->roleName() === RoleName::Agent ? 'agent' : 'agency';
        $resource = $panel === 'agent' ? AgentNotificationResource::class : AgencyNotificationResource::class;

        return view('livewire.related-match-list', [
            'count' => (int) $record->getAttribute('match_count'),
            'unreadCount' => (int) $record->getAttribute('unread_match_count'),
            'matches' => $notifications->map(function (MatchNotification $notification) use ($resource, $panel): array {
                $match = $notification->match;

                return [
                    'id' => $notification->getKey(),
                    'title' => $this->side === 'property' ? $match?->customer?->full_name : $match?->property?->title,
                    'score' => $match?->score,
                    'rank' => $match?->getAttribute($this->side.'_rank'),
                    'mode' => MatchNotificationResourceBase::matchModeLabel($match?->match_mode),
                    'reason' => RelatedMatchQuery::shortReason($match),
                    'isRead' => MatchNotificationResourceBase::isRead($notification),
                    'url' => $resource::getUrl('view', ['record' => $notification], panel: $panel),
                ];
            }),
        ]);
    }

    private function authorizedRecord(): Property|Customer
    {
        $actor = $this->actor();
        Gate::authorize('viewAny', MatchNotification::class);
        // Nested Livewire updates do not inherit the initial panel's tenant context.
        app(TenantContext::class)->establish($actor);
        $model = $this->side === 'property' ? Property::class : Customer::class;
        $record = $model::query()->withTrashed()->where('agency_id', $actor->agency_id)->find($this->recordId);
        abort_unless($record instanceof Property || $record instanceof Customer, 404);
        Gate::authorize('view', $record);

        return $record;
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        abort_unless($actor->is_active && ! $actor->trashed() && $actor->agency_id !== null && $actor->agency?->is_active, 403);
        $panel = $actor->roleName() === RoleName::Agent ? 'agent' : 'agency';
        abort_unless($actor->canAccessPanel(Filament::getPanel($panel)), 403);
        $currentPanel = Filament::getCurrentPanel();
        abort_unless($currentPanel === null || $currentPanel->getId() === $panel, 403);

        return $actor;
    }
}
