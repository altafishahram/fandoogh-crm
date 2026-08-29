<div wire:poll.30s="refreshUnread" class="melkban-notification-poller">
    <a href="{{ $notificationUrl }}" class="melkban-notification-button" aria-label="اعلان‌های تطبیق؛ {{ $unreadCount }} خوانده‌نشده">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
        </svg>
        @if ($unreadCount > 0)
            <span>{{ $unreadCount > 99 ? '۹۹+' : $unreadCount }}</span>
        @endif
    </a>
</div>
