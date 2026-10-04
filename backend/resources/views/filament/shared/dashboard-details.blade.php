<x-filament-widgets::widget>
    <div class="melkban-dashboard-flow" wire:poll.visible.30s="refreshMatches">
        <section class="melkban-dashboard-welcome" aria-labelledby="melkban-welcome-title">
            <div>
                <h2 id="melkban-welcome-title">سلام {{ auth()->user()?->name }}، خوش آمدید</h2>
                <p>کارهای آژانس، وضعیت املاک و آخرین فعالیت‌ها را در یک نگاه دنبال کنید.</p>
            </div>
        </section>

        <x-filament::section heading="دسترسی سریع">
            <div class="melkban-action-grid">
                <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['property_create'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><span>ثبت ملک جدید</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['customer_create'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><span>ثبت مشتری جدید</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['properties'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg></span><span>فهرست و جست‌وجوی املاک</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link" href="{{ $urls['customers'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87" /></svg></span><span>فهرست مشتریان</span>
                </a>
            </div>
        </x-filament::section>

        <div class="melkban-dashboard-columns">
            <x-filament::section heading="املاک اخیر">
                <div class="melkban-record-list">
                    @forelse ($data['recent_properties'] ?? [] as $property)
                        <article class="melkban-card melkban-record-card">
                            <a href="{{ $urls['properties'] }}/{{ $property['id'] }}" class="melkban-card-link">
                                <div class="melkban-record-heading">
                                    <strong>{{ $property['title'] }}</strong>
                                    <span class="melkban-record-status">{{ \App\Filament\Shared\Support\PersianLabels::value($property['status']) }}</span>
                                </div>
                                <div class="melkban-record-meta">
                                    <span>کد {{ $property['code'] }}</span>
                                    <span>·</span>
                                    <span>{{ \App\Filament\Shared\Support\PersianLabels::value($property['transaction_type']) }}</span>
                                    <span>·</span>
                                    <span>{{ \App\Filament\Shared\Support\PersianDate::format($property['updated_at']) }}</span>
                                </div>
                            </a>
                            @include('filament.shared.related-match-summary-badge', [
                                'summary' => $property['match_summary'] ?? null,
                                'matchUrl' => $urls['properties'].'/'.$property['id'].'#related-matches',
                            ])
                        </article>
                    @empty
                        <p class="melkban-empty-state">هنوز ملکی ثبت نشده است.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="آخرین یادداشت‌ها">
                <div class="melkban-record-list">
                    @forelse ($data['recent_notes'] ?? [] as $note)
                        <a href="{{ $urls['properties'] }}/{{ $note['property_id'] }}" class="melkban-card melkban-card-link melkban-record-card">
                            <strong>{{ $note['property_title'] ?? 'ملک' }}</strong>
                            <p class="melkban-note-preview">{{ $note['body'] }}</p>
                            <span class="melkban-record-meta">{{ \App\Filament\Shared\Support\PersianDate::format($note['created_at']) }}</span>
                        </a>
                    @empty
                        <p class="melkban-empty-state">هنوز یادداشتی ثبت نشده است.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
