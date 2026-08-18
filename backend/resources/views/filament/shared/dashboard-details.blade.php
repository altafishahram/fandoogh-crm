<x-filament-widgets::widget>
    <div class="space-y-6">
        <x-filament::section heading="دسترسی سریع">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <a class="melkban-card melkban-card-link p-4 font-semibold" href="{{ $urls['property_create'] }}">ثبت ملک جدید</a>
                <a class="melkban-card melkban-card-link p-4 font-semibold" href="{{ $urls['customer_create'] }}">ثبت مشتری جدید</a>
                <a class="melkban-card melkban-card-link p-4 font-semibold" href="{{ $urls['properties'] }}">فهرست و جست‌وجوی املاک</a>
                <a class="melkban-card melkban-card-link p-4 font-semibold" href="{{ $urls['customers'] }}">فهرست مشتریان</a>
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
