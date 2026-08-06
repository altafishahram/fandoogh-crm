<x-filament-widgets::widget>
    <x-filament::section heading="جزئیات عملیاتی">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($data as $label => $value)
                <section class="overflow-x-auto rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="mb-2 font-semibold">{{ \App\Filament\Shared\Support\PersianLabels::field($label) }}</h3>
                    @if (is_array($value))
                        <pre class="whitespace-pre-wrap text-sm">{{ json_encode(\App\Filament\Shared\Support\PersianLabels::data($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <p class="text-2xl font-semibold">{{ $value }}</p>
                    @endif
                </section>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
