<x-filament-panels::page>
    <dl class="melkban-card grid gap-4 p-6 sm:grid-cols-2">
        <div><dt class="text-sm text-gray-500">آژانس</dt><dd class="font-medium">{{ $agency->name }}</dd></div>
        <div><dt class="text-sm text-gray-500">ایمیل</dt><dd class="font-medium">{{ $agency->email }}</dd></div>
        <div><dt class="text-sm text-gray-500">تلفن</dt><dd class="font-medium">{{ $agency->phone }}</dd></div>
        <div><dt class="text-sm text-gray-500">نشانی</dt><dd class="font-medium">{{ $agency->address_line_1 }}، {{ $agency->city }}</dd></div>
        <div><dt class="text-sm text-gray-500">پیشوند کد ملک</dt><dd class="font-medium">{{ $agency->settings->property_code_prefix }}</dd></div>
        <div><dt class="text-sm text-gray-500">تعداد در هر صفحه</dt><dd class="font-medium">{{ $agency->settings->default_page_size }}</dd></div>
        <div><dt class="text-sm text-gray-500">منطقه زمانی</dt><dd class="font-medium">{{ $agency->timezone }}</dd></div>
        <div><dt class="text-sm text-gray-500">واحد پول</dt><dd class="font-medium">{{ $agency->currency_code }}</dd></div>
    </dl>
</x-filament-panels::page>
