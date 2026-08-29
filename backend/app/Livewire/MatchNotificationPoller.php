<?php

declare(strict_types=1);

namespace App\Livewire;

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
        $this->notificationUrl = Filament::getCurrentPanel()?->getId() === 'agent'
            ? AgentMatchNotificationResource::getUrl('index')
            : AgencyMatchNotificationResource::getUrl('index');
        $this->unreadCount = $this->countUnread();
        $this->lastKnownCount = $this->unreadCount;
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
        $user = auth()->user();
        if (! $user instanceof User || $user->agency_id === null) {
            return 0;
        }

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
