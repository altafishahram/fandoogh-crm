<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\MatchNotifications\MatchNotificationResource as AgencyMatchNotificationResource;
use App\Filament\Agent\Resources\MatchNotifications\MatchNotificationResource as AgentMatchNotificationResource;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Livewire\Component;

final class MatchNotificationPoller extends Component
{
    public int $unreadCount = 0;

    public int $lastKnownCount = 0;

    public string $notificationUrl = '';

    public function mount(): void
    {
        $this->unreadCount = $this->countUnread();
        $this->lastKnownCount = $this->unreadCount;
        $this->notificationUrl = match (Filament::getCurrentPanel()?->getId()) {
            'agent' => AgentMatchNotificationResource::getUrl('index'),
            'agency' => AgencyMatchNotificationResource::getUrl('index'),
            default => '',
        };
    }

    public function refreshUnread(): void
    {
        $count = $this->countUnread();
        if ($count > $this->lastKnownCount) {
            $newCount = $count - $this->lastKnownCount;
            Notification::make()
                ->title("{$newCount} اعلان تطبیق جدید دریافت شد")
                ->body('برای بررسی ملک و مشتری منطبق، اعلان‌ها را باز کنید.')
                ->warning()
                ->actions([
                    Action::make('view')
                        ->label('مشاهده اعلان‌ها')
                        ->url($this->notificationUrl),
                ])
                ->send();
        }

        $this->unreadCount = $count;
        $this->lastKnownCount = $count;
    }

    public function render(): View
    {
        return view('livewire.match-notification-poller');
    }

    private function countUnread(): int
    {
        $context = app(TenantContext::class);
        $context->clear();

        $user = auth()->user();
        $panel = Filament::getCurrentPanel();
        $expectedRole = match ($panel?->getId()) {
            'agency' => RoleName::AgencyManager,
            'agent' => RoleName::Agent,
            default => null,
        };

        if (! $user instanceof User || $panel === null || $expectedRole === null) {
            return 0;
        }

        // Direct component updates may bypass panel middleware; never trust a
        // stale actor, cached roles, or another tenant's request context.
        $user = $user->fresh();
        if ($user === null || ! $user->is_active || $user->trashed() || $user->agency_id === null) {
            return 0;
        }

        $roles = $user->roles()->pluck('name');
        if ($roles->count() !== 1 || $roles->first() !== $expectedRole->value || ! $user->canAccessPanel($panel)) {
            return 0;
        }

        $context->establish($user);

        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        return MatchNotification::query()
            ->where($notificationsTable.'.agency_id', $user->agency_id)
            ->whereNotExists(function (QueryBuilder $query) use ($notificationsTable, $readsTable, $user): void {
                $query->selectRaw('1')
                    ->from($readsTable)
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $user->agency_id)
                    ->where($readsTable.'.user_id', $user->getKey())
                    ->whereColumn($readsTable.'.notification_version', '>=', $notificationsTable.'.version');
            })
            ->count();
    }
}
