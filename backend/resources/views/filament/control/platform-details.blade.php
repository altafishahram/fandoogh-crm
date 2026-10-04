<x-filament-widgets::widget>
    <div class="melkban-dashboard-flow" wire:poll.visible.30s="refreshPlatform">
        <section class="melkban-dashboard-welcome" aria-labelledby="platform-welcome-title">
            <div>
                <h2 id="platform-welcome-title">سلام {{ auth()->user()?->name }}، خوش آمدید</h2>
                <p>مدیریت آژانس‌ها، مدیران و آمار کلی دفتر املاکی در یک نگاه.</p>
            </div>
        </section>

        <x-filament::section heading="دسترسی سریع مدیریت کل">
            <div class="melkban-action-grid">
                @can('create', \App\Models\Agency::class)
                    <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['agency_create'] }}">
                        <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><span>ثبت آژانس جدید</span>
                    </a>
                @endcan
                @can('create', \App\Models\User::class)
                    <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['manager_create'] }}">
                        <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4" /><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M20 8v6M17 11h6" /></svg></span><span>ثبت مدیر آژانس</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\Agency::class)
                    <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['agencies'] }}">
                        <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 21V3h12v18M1 21h22M8 7h4M8 11h4M8 15h4M16 9h4v12" /></svg></span><span>مدیریت آژانس‌ها</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\User::class)
                    <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['managers'] }}">
                        <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87" /></svg></span><span>مدیریت مدیران آژانس</span>
                    </a>
                @endcan
            </div>
        </x-filament::section>

        <x-filament::section heading="آمار آژانس‌ها" description="فقط تعداد املاک و مشتریان نمایش داده می‌شود؛ اطلاعات پرونده‌ها در اختیار آژانس مربوطه است.">
            <div class="melkban-record-list">
                @forelse ($data['agency_totals'] ?? [] as $agency)
                    <article class="melkban-card melkban-record-card">
                        <div class="melkban-record-heading">
                            @can('viewAny', \App\Models\Agency::class)
                                <a class="melkban-card-link" href="{{ $urls['agencies'] }}/{{ $agency['agency_id'] }}"><strong>{{ $agency['name'] }}</strong></a>
                            @else
                                <strong>{{ $agency['name'] }}</strong>
                            @endcan
                        </div>
                        <div class="melkban-record-meta">
                            <span>املاک: {{ number_format((int) $agency['properties']) }}</span>
                            <span aria-hidden="true">·</span>
                            <span>مشتریان: {{ number_format((int) $agency['customers']) }}</span>
                        </div>
                    </article>
                @empty
                    <p class="melkban-empty-state">هنوز آژانسی ثبت نشده است.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
