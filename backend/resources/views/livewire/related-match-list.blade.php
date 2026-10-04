<section id="related-matches" wire:poll.visible.30s class="melkban-related-matches melkban-card" aria-labelledby="related-matches-heading-{{ $this->getId() }}">
    <style>
        .melkban-related-matches { padding: 1.25rem; }
        .melkban-related-matches header, .melkban-related-match-heading, .melkban-related-match-status { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
        .melkban-related-matches h2, .melkban-related-matches h3 { font-weight: 800; color: var(--melkban-ink); }
        .melkban-related-matches p { color: var(--melkban-ink); line-height: 1.7; }
        .melkban-related-matches ol { display: grid; gap: .75rem; margin-block: 1rem; list-style: none; padding: 0; }
        .melkban-related-match { display: block; padding: 1rem; border: 1px solid var(--melkban-border); border-radius: 1rem; background: var(--melkban-surface-soft); overflow-wrap: anywhere; }
        .melkban-related-match:hover { border-color: var(--melkban-primary); }
        .melkban-related-match:focus-visible { outline: 3px solid var(--melkban-primary); outline-offset: 3px; }
        .melkban-related-match-heading { margin-bottom: .5rem; }
        .melkban-related-match-status { justify-content: flex-start; margin-top: .75rem; font-size: .875rem; }
        .melkban-related-match-count { color: var(--melkban-accent); font-weight: 700; }
        .melkban-related-match-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
    </style>
    <header>
        <h2 id="related-matches-heading-{{ $this->getId() }}">{{ $side === 'property' ? 'مشتریان منطبق' : 'املاک منطبق' }}</h2>
        <span class="melkban-related-match-count" role="status" aria-atomic="true" aria-label="{{ \App\Filament\Shared\Support\RelatedMatchPresentation::accessibleSummary($count, $unreadCount) }}">{{ \App\Filament\Shared\Support\RelatedMatchPresentation::summary($count, $unreadCount) }}</span>
    </header>
    <p>تا ۲۰ تطبیق برتر، به ترتیب رتبه. بازکردن این فهرست اعلان‌ها را خوانده‌شده نمی‌کند.</p>

    <ol>
        @forelse ($matches as $match)
            <li wire:key="related-match-{{ $match['id'] }}">
                <a href="{{ $match['url'] }}" class="melkban-related-match">
                    <div class="melkban-related-match-heading">
                        <h3>{{ \App\Filament\Shared\Support\RelatedMatchPresentation::number($match['rank']) }}. {{ $match['title'] }}</h3>
                        <x-filament::badge color="primary">امتیاز {{ \App\Filament\Shared\Support\RelatedMatchPresentation::number($match['score']) }} از ۱۰۰</x-filament::badge>
                    </div>
                    <p>{{ $match['reason'] ?: 'تطبیق شرایط ملک و نیاز مشتری با شیوه '.$match['mode'] }}</p>
                    <div class="melkban-related-match-status">
                        <x-filament::badge color="gray">شیوه مالی: {{ $match['mode'] }}</x-filament::badge>
                        <x-filament::badge :color="$match['isRead'] ? 'gray' : 'warning'">{{ $match['isRead'] ? 'خوانده‌شده' : 'خوانده‌نشده' }}</x-filament::badge>
                        <span>مشاهده جزئیات اعلان</span>
                    </div>
                </a>
            </li>
        @empty
            <li><p>هنوز تطبیق معتبری برای این {{ $side === 'property' ? 'ملک' : 'مشتری' }} ثبت نشده است.</p></li>
        @endforelse
    </ol>

    <div class="melkban-related-match-footer">
        <p>نمایش {{ \App\Filament\Shared\Support\RelatedMatchPresentation::number($matches->count()) }} از {{ \App\Filament\Shared\Support\RelatedMatchPresentation::number($count) }} تطبیق</p>
        @if ($matches->count() < $count && $visibleCount < 20)
            <x-filament::button wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" style="min-height: 44px">
                <span wire:loading.remove wire:target="loadMore">نمایش ۱۰ تطبیق بعدی</span>
                <span wire:loading wire:target="loadMore">در حال دریافت…</span>
            </x-filament::button>
        @endif
    </div>
</section>
