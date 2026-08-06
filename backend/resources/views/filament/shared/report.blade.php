<x-filament-panels::page>
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <label class="grid gap-1">
            <span class="text-sm font-medium">از تاریخ</span>
            <input name="from" type="date" value="{{ $from }}" required class="fi-input rounded-lg border-gray-300" />
        </label>
        <label class="grid gap-1">
            <span class="text-sm font-medium">تا تاریخ</span>
            <input name="to" type="date" value="{{ $to }}" required class="fi-input rounded-lg border-gray-300" />
        </label>
        <x-filament::button type="submit">تهیه گزارش</x-filament::button>
    </form>

    @if ($error !== '')
        <div class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-300">
            {{ $error }}
        </div>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach (collect($report)->except('period') as $label => $value)
            <section class="melkban-card overflow-x-auto p-4">
                <h2 class="mb-3 font-semibold">{{ \App\Filament\Shared\Support\PersianLabels::field($label) }}</h2>
                @if (! is_array($value))
                    <p class="text-3xl font-semibold">{{ $value }}</p>
                @elseif (array_is_list($value) && $value !== [])
                    <table class="w-full text-right text-sm">
                        <thead><tr>
                            @foreach (array_keys($value[0]) as $heading)
                                <th class="border-b p-2">{{ \App\Filament\Shared\Support\PersianLabels::field($heading) }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($value as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td class="border-b p-2">{{ is_array($cell) ? json_encode($cell, JSON_UNESCAPED_UNICODE) : \App\Filament\Shared\Support\PersianLabels::value($cell) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="w-full text-right text-sm">
                        <tbody>
                            @forelse ($value as $key => $count)
                                <tr>
                                    <th class="border-b p-2 font-medium">{{ \App\Filament\Shared\Support\PersianLabels::value($key) }}</th>
                                    <td class="border-b p-2 text-right">{{ is_array($count) ? json_encode($count, JSON_UNESCAPED_UNICODE) : $count }}</td>
                                </tr>
                            @empty
                                <tr><td class="p-2 text-gray-500">داده‌ای برای نمایش وجود ندارد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
