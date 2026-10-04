@if (is_array($summary))
    <div class="melkban-match-summary">
        <x-filament::badge
            tag="a"
            :href="$matchUrl"
            :color="$summary['unread_count'] > 0 ? 'warning' : 'gray'"
            :aria-label="\App\Filament\Shared\Support\RelatedMatchPresentation::accessibleSummary($summary['count'], $summary['unread_count'])"
            style="min-height: 44px; width: fit-content; max-width: 100%; white-space: normal"
        >
            {{ \App\Filament\Shared\Support\RelatedMatchPresentation::summary($summary['count'], $summary['unread_count']) }}
        </x-filament::badge>
    </div>
@endif
