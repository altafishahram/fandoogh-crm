<x-filament-panels::page>
    <form method="GET" class="flex items-end gap-4">
        <label class="grid flex-1 gap-1">
            <span class="text-sm font-medium">عبارت جست‌وجو (۲ تا ۱۰۰ نویسه)</span>
            <input name="q" type="search" minlength="2" maxlength="100" value="{{ $query }}" class="fi-input rounded-lg border-gray-300" />
        </label>
        <x-filament::button type="submit">جست‌وجو</x-filament::button>
    </form>

    @foreach ($results as $entity => $rows)
        <section class="melkban-card mt-6 p-4">
            <h2 class="mb-3 text-lg font-semibold">{{ \App\Filament\Shared\Support\PersianLabels::field($entity) }} ({{ count($rows) }})</h2>
            @forelse ($rows as $row)
                <div class="border-t border-gray-200 py-2 text-sm first:border-0 dark:border-gray-700">
                    {{ collect($row)->except(['id'])->map(fn ($value, $key) => \App\Filament\Shared\Support\PersianLabels::field($key).': '.\App\Filament\Shared\Support\PersianLabels::value($value))->implode(' · ') }}
                </div>
            @empty
                <p class="text-sm text-gray-500">نتیجه‌ای پیدا نشد.</p>
            @endforelse
        </section>
    @endforeach
</x-filament-panels::page>
