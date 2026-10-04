@if (in_array(\Filament\Facades\Filament::getCurrentPanel()?->getId(), ['agency', 'agent'], true))
    <livewire:match-notification-poller />
@endif
