<x-filament-panels::page>
    <div class="melkban-marketplace space-y-6" dir="rtl">
        <p class="text-sm text-gray-600 dark:text-gray-300">آگهی‌های قابل همکاری از آژانس‌های تأییدشده؛ فقط محله و مشخصات قابل انتشار نمایش داده می‌شود.</p>
        <div class="melkban-marketplace-filters grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-2"><span>جستجوی عنوان</span><input type="search" wire:model.live.debounce.400ms="search" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" /></label>
            <label class="space-y-2"><span>استان</span><select wire:model.live="provinceFilter" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">همه استان‌ها</option>@foreach(\Illuminate\Support\Facades\DB::table('location_provinces')->orderBy('name')->get() as $region)<option value="{{ $region->id }}">{{ $region->name }}</option>@endforeach</select></label>
            <label class="space-y-2"><span>شهرستان</span><select wire:model.live="countyFilter" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">همه شهرستان‌ها</option>@foreach(\Illuminate\Support\Facades\DB::table('location_counties')->where('province_id', $provinceFilter)->orderBy('name')->get() as $region)<option value="{{ $region->id }}">{{ $region->name }}</option>@endforeach</select></label>
            <label class="space-y-2"><span>شهر</span><select wire:model.live="cityFilter" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">همه شهرها</option>@foreach(\Illuminate\Support\Facades\DB::table('location_cities')->where('county_id', $countyFilter)->orderBy('name')->get() as $region)<option value="{{ $region->id }}">{{ $region->name }}</option>@endforeach</select></label>
        </div>
        @if($selectedListingClosed)<p role="status" class="rounded-lg border p-4">این آگهی دیگر در دسترس نیست. می‌توانید آگهی دیگری انتخاب کنید.</p>@endif
        @if($selected)
            <section aria-label="جزئیات آگهی" class="melkban-listing-detail rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700 space-y-4">
                <div class="melkban-marketplace-actions flex justify-between gap-4"><h2 class="text-xl font-bold">{{ $selected->public_title }}</h2><x-filament::button color="gray" wire:click="$set('selectedListing', null)">بستن جزئیات</x-filament::button></div>
                <p>{{ $selected->property->district ?: 'محله اعلام نشده' }}</p>
                <dl class="melkban-listing-specs grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt>نوع ملک</dt><dd>{{ \App\Filament\Shared\Support\PersianLabels::value($selected->property->property_type) }}</dd></div>
                    <div><dt>نوع معامله</dt><dd>{{ \App\Filament\Shared\Support\PersianLabels::value($selected->property->transaction_type) }}</dd></div>
                    <div><dt>مساحت</dt><dd>{{ $selected->property->area_sqm }} متر مربع</dd></div>
                    <div><dt>اتاق خواب</dt><dd>{{ $selected->property->bedrooms ?? '—' }}</dd></div>
                    @foreach(['year_built'=>'سال ساخت','bathrooms'=>'سرویس بهداشتی','floor_number'=>'طبقه','total_floors'=>'کل طبقات','parking_spaces'=>'پارکینگ','units_per_floor'=>'واحد در طبقه','master_bedrooms'=>'اتاق مستر','cabinet_type'=>'کابینت','heating_type'=>'گرمایش','cooling_type'=>'سرمایش','flooring_type'=>'کف‌پوش','renovation_status'=>'بازسازی','building_orientation'=>'جهت ساختمان','deed_type'=>'سند','building_type'=>'نوع ساختمان','structure_type'=>'سازه','land_area'=>'مساحت زمین','building_area'=>'زیربنا','land_frontage'=>'بر زمین'] as $field=>$label)
                        @if($selected->property->$field !== null)<div><dt>{{ $label }}</dt><dd>{{ \App\Filament\Shared\Support\PersianLabels::value($selected->property->$field) }}</dd></div>@endif
                    @endforeach
                    @foreach(['has_storage_room'=>'انباری','has_elevator'=>'آسانسور','has_balcony'=>'بالکن','has_loan'=>'وام','is_exchangeable'=>'معاوضه','has_pool'=>'استخر','has_jacuzzi'=>'جکوزی','has_sauna'=>'سونا','has_water'=>'آب','has_electricity'=>'برق','has_gas'=>'گاز','can_aggregate'=>'تجمیع','is_convertible'=>'تبدیل ودیعه و اجاره'] as $field=>$label)
                        @if($selected->property->$field !== null)<div><dt>{{ $label }}</dt><dd>{{ $selected->property->$field ? 'دارد' : 'ندارد' }}</dd></div>@endif
                    @endforeach
                    @foreach(['sale_price'=>'قیمت فروش','deposit_amount'=>'ودیعه','monthly_rent'=>'اجاره ماهانه'] as $field=>$label)
                        @if($selected->property->$field !== null)<div><dt>{{ $label }}</dt><dd>{{ number_format((float) $selected->property->$field) }} {{ $selected->property->currency_unit === 'toman' ? 'تومان' : ($selected->property->currency_code === 'IRR' ? 'ریال' : $selected->property->currency_code) }}</dd></div>@endif
                    @endforeach
                </dl>
                <p class="melkban-public-copy whitespace-pre-wrap">{{ $selected->public_description }}</p>
                <div class="melkban-listing-gallery grid gap-4 sm:grid-cols-3">@foreach($selected->images as $image)<img src="{{ route('marketplace.web.listing-image', ['listing'=>$selected->id, 'image'=>$image->id]) }}" alt="تصویر ملک {{ $selected->public_title }}" loading="lazy" class="aspect-video w-full rounded-lg object-cover" />@endforeach</div>
                <p>آژانس: {{ $selected->agency->name }}</p>
                <div class="melkban-marketplace-actions flex flex-wrap gap-3"><x-filament::button tag="a" href="tel:{{ $selected->agency->phone }}">تماس با آژانس</x-filament::button><x-filament::button wire:click="startConversation" wire:loading.attr="disabled">شروع گفتگو</x-filament::button></div>
            </section>
        @endif
        <div wire:loading.delay class="text-sm" role="status">در حال دریافت اطلاعات…</div>
        <div class="melkban-marketplace-cards grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($listings as $listing)
                <article wire:key="listing-{{ $listing->id }}" class="melkban-listing-card rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700 space-y-3">
                    @if($image=$listing->images->first())<img src="{{ route('marketplace.web.listing-image', ['listing'=>$listing->id, 'image'=>$image->id]) }}" alt="تصویر ملک {{ $listing->public_title }}" loading="lazy" class="aspect-video w-full rounded-lg object-cover" />@endif
                    <h2 class="text-lg font-bold">{{ $listing->public_title }}</h2>
                    <p>{{ $listing->property->district }} · {{ $listing->property->area_sqm }} متر</p>
                    <p>{{ \App\Filament\Shared\Support\PersianLabels::value($listing->property->property_type) }} · {{ \App\Filament\Shared\Support\PersianLabels::value($listing->property->transaction_type) }}</p>
                    @foreach(['sale_price'=>'قیمت فروش','deposit_amount'=>'ودیعه','monthly_rent'=>'اجاره ماهانه'] as $field=>$label)
                        @if($listing->property->$field !== null)<p>{{ $label }}: {{ number_format((float) $listing->property->$field) }} {{ $listing->property->currency_unit === 'toman' ? 'تومان' : ($listing->property->currency_code === 'IRR' ? 'ریال' : $listing->property->currency_code) }}</p>@endif
                    @endforeach
                    <p>{{ $listing->agency->name }}</p>
                    <x-filament::button color="gray" wire:click="selectListing({{ $listing->id }})">مشاهده جزئیات</x-filament::button>
                </article>
            @empty<p class="col-span-full rounded-xl border p-6">آگهی قابل همکاری با این فیلترها پیدا نشد.</p>@endforelse
        </div>
        {{ $listings->links() }}
    </div>
</x-filament-panels::page>
