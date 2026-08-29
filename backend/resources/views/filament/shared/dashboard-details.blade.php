<x-filament-widgets::widget>
    <div class="space-y-6">
        <section class="melkban-dashboard-welcome" aria-labelledby="melkban-welcome-title">
            <div>
                <h2 id="melkban-welcome-title">سلام {{ auth()->user()?->name }}، خوش آمدید</h2>
                <p>کارهای آژانس، وضعیت املاک و آخرین فعالیت‌ها را در یک نگاه دنبال کنید.</p>
            </div>
        </section>

        <x-filament::section heading="دسترسی سریع">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <a class="melkban-card melkban-card-link melkban-quick-link p-4 font-semibold" href="{{ $urls['property_create'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><span>ثبت ملک جدید</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link p-4 font-semibold" href="{{ $urls['customer_create'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></span><span>ثبت مشتری جدید</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link p-4 font-semibold" href="{{ $urls['properties'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg></span><span>فهرست و جست‌وجوی املاک</span>
                </a>
                <a class="melkban-card melkban-card-link melkban-quick-link p-4 font-semibold" href="{{ $urls['customers'] }}">
                    <span class="melkban-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87" /></svg></span><span>فهرست مشتریان</span>
                </a>
            </div>
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section heading="املاک اخیر">
                <div class="space-y-3">
                    @forelse ($data['recent_properties'] ?? [] as $property)
                        <a href="{{ $urls['properties'] }}/{{ $property['id'] }}" class="melkban-card melkban-card-link p-4">
                            <div class="flex items-center justify-between gap-3">
                                <strong>{{ $property['title'] }}</strong>
                                <span class="text-xs text-gray-500">{{ \App\Filament\Shared\Support\PersianLabels::value($property['status']) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-500">
                                <span>کد {{ $property['code'] }}</span>
                                <span>·</span>
                                <span>{{ \App\Filament\Shared\Support\PersianLabels::value($property['transaction_type']) }}</span>
                                <span>·</span>
                                <span>{{ \App\Filament\Shared\Support\PersianDate::format($property['updated_at']) }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">هنوز ملکی ثبت نشده است.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="آخرین یادداشت‌ها">
                <div class="space-y-3">
                    @forelse ($data['recent_notes'] ?? [] as $note)
                        <a href="{{ $urls['properties'] }}/{{ $note['property_id'] }}" class="melkban-card melkban-card-link p-4">
                            <strong class="text-sm">{{ $note['property_title'] ?? 'ملک' }}</strong>
                            <p class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $note['body'] }}</p>
                            <span class="mt-2 block text-xs text-gray-500">{{ \App\Filament\Shared\Support\PersianDate::format($note['created_at']) }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">هنوز یادداشتی ثبت نشده است.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
