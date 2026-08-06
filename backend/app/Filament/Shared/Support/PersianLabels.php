<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use BackedEnum;

final class PersianLabels
{
    /** @var array<string, string> */
    private const FIELDS = [
        'name' => 'نام', 'slug' => 'شناسه انگلیسی', 'email' => 'ایمیل', 'phone' => 'تلفن',
        'mobile' => 'شماره همراه', 'address_line_1' => 'نشانی اصلی', 'address_line_2' => 'ادامه نشانی',
        'city' => 'شهر', 'province' => 'استان', 'postal_code' => 'کد پستی', 'country_code' => 'کد کشور',
        'timezone' => 'منطقه زمانی', 'locale' => 'زبان', 'currency_code' => 'واحد پول',
        'property_code_prefix' => 'پیشوند کد ملک', 'default_page_size' => 'تعداد در هر صفحه',
        'agency_id' => 'آژانس', 'temporary_password' => 'رمز عبور موقت', 'is_active' => 'فعال',
        'must_change_password' => 'نیازمند تغییر رمز', 'last_login_at' => 'آخرین ورود',
        'owner_type' => 'نوع مالک', 'first_name' => 'نام', 'last_name' => 'نام خانوادگی',
        'company_name' => 'نام شرکت', 'identity_number_encrypted' => 'شماره شناسایی', 'notes' => 'یادداشت‌ها',
        'title' => 'عنوان', 'description' => 'توضیحات', 'property_type' => 'نوع ملک',
        'transaction_type' => 'نوع معامله', 'assigned_agent_id' => 'کارشناس مسئول',
        'assignedAgent.name' => 'کارشناس مسئول', 'sale_price' => 'قیمت فروش', 'deposit_amount' => 'مبلغ ودیعه',
        'monthly_rent' => 'اجاره ماهانه', 'area_sqm' => 'مساحت (متر مربع)', 'bedrooms' => 'تعداد اتاق خواب',
        'bathrooms' => 'تعداد سرویس', 'floor_number' => 'طبقه', 'total_floors' => 'تعداد کل طبقات',
        'year_built' => 'سال ساخت', 'parking_spaces' => 'تعداد پارکینگ', 'has_storage_room' => 'انباری دارد',
        'has_elevator' => 'آسانسور دارد', 'has_balcony' => 'بالکن دارد', 'district' => 'محله',
        'street_address' => 'نشانی ملک', 'latitude' => 'عرض جغرافیایی', 'longitude' => 'طول جغرافیایی',
        'available_from' => 'تاریخ آماده تحویل', 'owners' => 'مالکیت‌ها', 'owner_id' => 'مالک',
        'ownership_percentage' => 'درصد مالکیت', 'is_primary' => 'مالک اصلی', 'code' => 'کد ملک',
        'status' => 'وضعیت', 'preferred_contact_method' => 'روش تماس ترجیحی', 'intent' => 'نوع درخواست',
        'preferred_property_types' => 'انواع ملک ترجیحی', 'budget_min' => 'حداقل بودجه',
        'budget_max' => 'حداکثر بودجه', 'desired_city' => 'شهر موردنظر', 'desired_district' => 'محله موردنظر',
        'min_area_sqm' => 'حداقل مساحت', 'max_area_sqm' => 'حداکثر مساحت',
        'min_bedrooms' => 'حداقل اتاق خواب', 'converted_property_id' => 'ملک نهایی‌شده',
        'module' => 'بخش', 'filters' => 'فیلترها', 'sort' => 'مرتب‌سازی', 'is_default' => 'فیلتر پیش‌فرض',
        'created_at' => 'زمان ایجاد', 'updated_at' => 'آخرین تغییر', 'activated_at' => 'زمان فعال‌سازی',
        'body' => 'متن یادداشت', 'author.name' => 'ثبت‌کننده', 'action' => 'رویداد',
        'from_status' => 'وضعیت قبلی', 'to_status' => 'وضعیت جدید', 'reason' => 'دلیل',
        'actor.name' => 'تغییردهنده', 'occurred_at' => 'زمان رویداد', 'original_name' => 'نام فایل',
        'mime_type' => 'نوع فایل', 'size_bytes' => 'حجم (بایت)', 'width' => 'عرض', 'height' => 'ارتفاع',
        'is_cover' => 'تصویر اصلی', 'sort_order' => 'ترتیب نمایش',
        'agency.name' => 'آژانس', 'users_count' => 'کاربران', 'properties_count' => 'املاک',
        'customers_count' => 'مشتریان', 'assigned_properties_count' => 'املاک واگذارشده',
        'assigned_customers_count' => 'مشتریان واگذارشده',
        'id' => 'شناسه', 'agent_id' => 'شناسه کارشناس',
        'property_id' => 'شناسه ملک', 'changed_by_user_id' => 'شناسه تغییردهنده', 'count' => 'تعداد',
        'agencies' => 'آژانس‌ها', 'users' => 'کاربران', 'agency_totals' => 'آمار آژانس‌ها',
        'properties_by_status' => 'املاک بر اساس وضعیت',
        'customers_by_status_intent' => 'مشتریان بر اساس وضعیت و درخواست',
        'unassigned_properties' => 'املاک بدون کارشناس', 'agent_workload' => 'حجم کار کارشناسان',
        'recent_history' => 'آخرین تغییرات', 'active_customers' => 'مشتریان فعال',
        'recent_properties' => 'آخرین املاک', 'recent_notes' => 'آخرین یادداشت‌ها',
        'properties_by_type' => 'املاک بر اساس نوع', 'properties_by_transaction' => 'املاک بر اساس معامله',
        'properties_by_agent' => 'املاک کارشناسان', 'new_properties' => 'املاک جدید',
        'closed_properties' => 'معاملات نهایی‌شده', 'customers_by_status' => 'مشتریان بر اساس وضعیت',
        'customers_by_intent' => 'مشتریان بر اساس درخواست', 'customers_by_agent' => 'مشتریان کارشناسان',
        'assigned_properties' => 'املاک واگذارشده', 'property_status_changes' => 'تغییرات وضعیت ملک',
        'customers_created' => 'مشتریان ثبت‌شده', 'properties' => 'املاک', 'customers' => 'مشتریان',
        'managers' => 'مدیران', 'agents' => 'کارشناسان', 'suspended' => 'تعلیق‌شده',
    ];

    /** @var array<string, string> */
    private const VALUES = [
        'person' => 'شخص', 'company' => 'شرکت',
        'apartment' => 'آپارتمان', 'house' => 'خانه', 'villa' => 'ویلا', 'land' => 'زمین',
        'office' => 'اداری', 'commercial' => 'تجاری', 'warehouse' => 'انبار', 'other' => 'سایر',
        'sale' => 'فروش', 'rent' => 'اجاره', 'available' => 'موجود', 'reserved' => 'رزروشده',
        'sold' => 'فروخته‌شده', 'rented' => 'اجاره‌رفته', 'archived' => 'بایگانی‌شده',
        'active' => 'فعال', 'inactive' => 'غیرفعال', 'converted' => 'نهایی‌شده', 'lost' => 'از دست‌رفته',
        'buy' => 'خرید', 'phone' => 'تلفن', 'email' => 'ایمیل',
        'properties' => 'املاک', 'owners' => 'مالکان', 'customers' => 'مشتریان',
        'created' => 'ایجاد', 'updated' => 'ویرایش', 'status_changed' => 'تغییر وضعیت',
        'unarchived' => 'خروج از بایگانی', 'deleted' => 'حذف', 'restored' => 'بازیابی',
        'owners_changed' => 'تغییر مالکان', 'assignment_changed' => 'تغییر کارشناس',
        'images_changed' => 'تغییر تصاویر',
    ];

    public static function field(string $name): string
    {
        return self::FIELDS[$name] ?? $name;
    }

    public static function value(mixed $value): string
    {
        $key = $value instanceof BackedEnum ? (string) $value->value : (string) $value;

        return self::VALUES[$key] ?? $key;
    }

    public static function data(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_string($value) || $value instanceof BackedEnum ? self::value($value) : $value;
        }

        $translated = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $label = self::field($key);
                $key = $label === $key ? self::value($key) : $label;
            }
            $translated[$key] = self::data($item);
        }

        return $translated;
    }

    /** @param array<int, BackedEnum> $cases
     * @return array<string, string>
     */
    public static function options(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[(string) $case->value] = self::value($case);
        }

        return $options;
    }
}
