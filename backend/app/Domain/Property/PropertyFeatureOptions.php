<?php

declare(strict_types=1);

namespace App\Domain\Property;

final class PropertyFeatureOptions
{
    /** @var array<string, string> */
    public const BUILDING_TYPES = [
        'detached' => 'ویلای مستقل',
        'township' => 'شهرکی',
        'duplex' => 'دوبلکس',
        'triplex' => 'تریبلکس',
        'apartment_villa' => 'ویلا آپارتمانی',
    ];

    public const TOILET_TYPES = ['iranian' => 'ایرانی', 'western' => 'فرنگی'];

    public const CABINET_TYPES = [
        'mdf' => 'MDF',
        'high_gloss' => 'های‌گلاس',
        'membrane' => 'ممبران',
        'wood' => 'چوب',
        'metal' => 'فلزی',
        'metal_wood_doors' => 'فلزی درب چوبی',
        'other' => 'سایر',
    ];

    public const HEATING_TYPES = [
        'heater' => 'بخاری',
        'radiator' => 'شوفاژ',
        'floor_heating' => 'گرمایش از کف',
        'duct_split' => 'داکت اسپلیت',
        'fireplace' => 'شومینه',
        'split' => 'اسپلیت',
        'fan_coil' => 'فن کوئل',
    ];

    public const COOLING_TYPES = [
        'water_cooler' => 'کولر آبی',
        'air_conditioner' => 'کولر گازی',
        'duct_split' => 'داکت اسپلیت',
        'split' => 'اسپلیت',
        'other' => 'سایر',
    ];

    public const FLOORING_TYPES = [
        'ceramic' => 'سرامیک',
        'stone' => 'سنگ',
        'carpet' => 'موکت',
        'parquet' => 'پارکت',
        'pvc' => 'کف‌پوش PVC',
        'mosaic' => 'موزائیک',
        'cement' => 'سیمان',
    ];

    public const RENOVATION_STATUSES = [
        'new' => 'نوساز',
        'renovated' => 'بازسازی‌شده',
        'needs_renovation' => 'نیاز به بازسازی',
        'painted' => 'رنگ‌شده',
        'wallpaper' => 'کاغذ دیواری',
    ];

    public const ORIENTATIONS = [
        'north' => 'شمالی',
        'south' => 'جنوبی',
        'east' => 'شرقی',
        'west' => 'غربی',
    ];

    public const DEED_TYPES = [
        'single_page' => 'سند تک‌برگ',
        'booklet' => 'منگوله‌دار',
        'contract' => 'قولنامه‌ای',
        'other' => 'سایر',
    ];

    private function __construct() {}
}
