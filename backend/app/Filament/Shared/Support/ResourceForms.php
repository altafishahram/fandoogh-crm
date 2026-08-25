<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Property\PropertyFeatureOptions;
use App\Domain\SavedFilter\Enums\FilterModule;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Validation\ClosureValidationRule;

final class ResourceForms
{
    /** @return list<mixed> */
    public static function agency(): array
    {
        return [
            TextInput::make('name')->label(PersianLabels::field('name'))->required()->maxLength(160),
            TextInput::make('slug')->label(PersianLabels::field('slug'))->required()->alphaDash()->maxLength(100)
                ->disabled(fn (?Agency $record): bool => $record instanceof Agency && $record->is_active),
            TextInput::make('email')->label(PersianLabels::field('email'))->email()->required()->maxLength(255),
            TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->required()->maxLength(30),
            TextInput::make('address_line_1')->label(PersianLabels::field('address_line_1'))->required()->maxLength(255),
            TextInput::make('address_line_2')->label(PersianLabels::field('address_line_2'))->maxLength(255),
            TextInput::make('city')->label(PersianLabels::field('city'))->required()->maxLength(100),
            TextInput::make('province')->label(PersianLabels::field('province'))->required()->maxLength(100),
            TextInput::make('postal_code')->label(PersianLabels::field('postal_code'))->maxLength(20),
            TextInput::make('country_code')->label(PersianLabels::field('country_code'))->required()->length(2)->default('IR'),
            TextInput::make('timezone')->label(PersianLabels::field('timezone'))->required()->default('Asia/Tehran'),
            TextInput::make('locale')->label(PersianLabels::field('locale'))->required()->default('fa'),
            TextInput::make('currency_code')->label(PersianLabels::field('currency_code'))->required()->length(3)->default('IRR'),
            TextInput::make('property_code_prefix')->label(PersianLabels::field('property_code_prefix'))->required()->minLength(2)->maxLength(10)
                ->regex('/^[A-Za-z0-9]{2,10}$/')
                ->validationMessages(['regex' => 'پیشوند کد ملک باید شامل ۲ تا ۱۰ حرف انگلیسی یا عدد باشد.'])
                ->helperText('فقط حروف انگلیسی یا عدد؛ نمونه: MLK'),
            Select::make('default_page_size')->label(PersianLabels::field('default_page_size'))->required()->options([10 => '10', 25 => '25', 50 => '50', 100 => '100'])->default(25),
        ];
    }

    /** @return list<mixed> */
    public static function user(bool $platform): array
    {
        $fields = [];
        if ($platform) {
            $fields[] = Select::make('agency_id')->label(PersianLabels::field('agency_id'))->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->options(fn (): array => Agency::query()->orderBy('name')->pluck('name', 'id')->all());
        }

        $fields = [
            ...$fields,
            TextInput::make('name')->label(PersianLabels::field('name'))->required()->maxLength(160),
            TextInput::make('email')->label(PersianLabels::field('email'))->email()->required()->maxLength(255),
            TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->maxLength(30),
            TextInput::make('temporary_password')->label(PersianLabels::field('temporary_password'))->password()->revealable()->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
        ];

        if (! $platform) {
            $fields[] = CheckboxList::make('permissions')
                ->label('دسترسی‌های کارشناس')
                ->helperText('حذف و تغییر وضعیت به‌صورت پیش‌فرض غیرفعال هستند.')
                ->options(PermissionLabels::agentOptions())
                ->default(array_map(static fn ($permission): string => $permission->value, RoleName::agentDefaultPermissions()))
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull();
        }

        return $fields;
    }

    /** @return list<mixed> */
    public static function owner(): array
    {
        return [
            Select::make('owner_type')->label(PersianLabels::field('owner_type'))->required()->options(PersianLabels::options(OwnerType::cases())),
            TextInput::make('first_name')->label(PersianLabels::field('first_name'))->maxLength(100), TextInput::make('last_name')->label(PersianLabels::field('last_name'))->maxLength(100),
            TextInput::make('company_name')->label(PersianLabels::field('company_name'))->maxLength(180), TextInput::make('mobile')->label(PersianLabels::field('mobile'))->tel()->maxLength(30),
            TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->maxLength(30), TextInput::make('email')->label(PersianLabels::field('email'))->email()->maxLength(255),
            TextInput::make('identity_number_encrypted')->label(PersianLabels::field('identity_number_encrypted'))->maxLength(100),
            TextInput::make('address_line_1')->label(PersianLabels::field('address_line_1'))->maxLength(255), TextInput::make('address_line_2')->label(PersianLabels::field('address_line_2'))->maxLength(255),
            TextInput::make('city')->label(PersianLabels::field('city'))->maxLength(100), TextInput::make('province')->label(PersianLabels::field('province'))->maxLength(100),
            TextInput::make('postal_code')->label(PersianLabels::field('postal_code'))->maxLength(20), Textarea::make('notes')->label(PersianLabels::field('notes'))->maxLength(4000)->columnSpanFull(),
        ];
    }

    /** @return list<mixed> */
    public static function property(bool $agent): array
    {
        return [
            Wizard::make([
                Step::make('مشخصات اصلی')->description('نوع معامله، نوع ملک و محل')->schema([
                    Hidden::make('lock_version'),
                    Select::make('transaction_type')->label('نوع معامله')->required()
                        ->options(fn (Get $get): array => $get('property_type') === PropertyType::LandOldBuilding->value
                            ? [TransactionType::Sale->value => 'فروش']
                            : PersianLabels::options(TransactionType::cases()))->live()
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                            if ($state === TransactionType::Rent->value) {
                                $set('year_built', null);
                                $set('has_loan', false);
                                $set('is_exchangeable', false);
                            }
                            $delivery = (string) ($get('delivery_status') ?? '');
                            $valid = $state === TransactionType::Sale->value
                                ? in_array($delivery, [
                                    DeliveryStatus::OwnerOccupied->value,
                                    DeliveryStatus::TenantOccupied->value,
                                    DeliveryStatus::Ready->value,
                                    DeliveryStatus::Vacated->value,
                                ], true)
                                : in_array($delivery, [
                                    DeliveryStatus::Ready->value,
                                    DeliveryStatus::Vacated->value,
                                    DeliveryStatus::Dated->value,
                                ], true);
                            if (! $valid) {
                                $set('delivery_status', DeliveryStatus::Ready->value);
                                $set('evacuation_date_display', null);
                            }
                        })
                        ->disabled(fn (string $operation): bool => $agent && $operation === 'edit'),
                    Select::make('property_type')->label('نوع ملک')->required()
                        ->options(self::propertyTypeOptions())->live()
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                            if (! self::isHouseVilla($state)) {
                                $set('building_type', null);
                            }
                            if ($state === PropertyType::LandOldBuilding->value) {
                                $set('transaction_type', TransactionType::Sale->value);
                                if (! in_array($get('delivery_status'), [
                                    DeliveryStatus::OwnerOccupied->value,
                                    DeliveryStatus::TenantOccupied->value,
                                    DeliveryStatus::Ready->value,
                                    DeliveryStatus::Vacated->value,
                                ], true)) {
                                    $set('delivery_status', DeliveryStatus::Ready->value);
                                    $set('evacuation_date_display', null);
                                }
                                foreach (self::buildingFields() as $field) {
                                    $set($field, null);
                                }
                                foreach (['has_storage_room', 'has_elevator', 'has_balcony'] as $field) {
                                    $set($field, false);
                                }
                                $set('parking_spaces', 0);
                                foreach (['toilet_types', 'has_master_bathroom', 'cabinet_type', 'heating_type',
                                    'cooling_type', 'flooring_type', 'renovation_status', 'building_orientation',
                                    'has_pool', 'has_jacuzzi', 'has_sauna', 'building_type', 'structure_type',
                                    'has_water', 'has_electricity', 'has_gas', 'telephone_line_count', 'land_area',
                                    'building_area'] as $field) {
                                    $set($field, in_array($field, ['has_master_bathroom', 'has_pool', 'has_jacuzzi', 'has_sauna',
                                        'has_water', 'has_electricity', 'has_gas'], true) ? false : null);
                                }
                            }
                            if ($state !== PropertyType::LandOldBuilding->value) {
                                $set('can_aggregate', false);
                                $set('land_frontage', null);
                            }
                            if ($state !== PropertyType::Industrial->value) {
                                foreach (['structure_type', 'telephone_line_count', 'land_area', 'building_area'] as $field) {
                                    $set($field, null);
                                }
                                foreach (['has_water', 'has_electricity', 'has_gas'] as $field) {
                                    $set($field, false);
                                }
                            }
                        }),
                    Select::make('building_type')->label('نوع بنا')
                        ->options(PropertyFeatureOptions::BUILDING_TYPES)
                        ->required(fn (Get $get): bool => self::isHouseVilla($get('property_type')))
                        ->visible(fn (Get $get): bool => self::isHouseVilla($get('property_type'))),
                    TextInput::make('title')->label('عنوان ملک')->required()->maxLength(200)->columnSpanFull(),
                    Select::make('status')->label('وضعیت ملک')->options(PersianLabels::options(PropertyStatus::cases()))
                        ->default(PropertyStatus::Available->value),
                    ...($agent ? [] : [Select::make('assigned_agent_id')->label('کارشناس مسئول')->searchable()->options(fn (): array => self::agentOptions())]),
                    TextInput::make('city')->label('شهر')->maxLength(100),
                    TextInput::make('district')->label('محله')->required()->maxLength(100),
                ])->columns(2),
                Step::make('مبلغ و نشانی')->description('مبالغ، متراژ و وضعیت تحویل')->schema([
                    TextInput::make('area_sqm')->label('مساحت (متر مربع)')->required()->numeric()->minValue(0.01)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            self::recalculateSalePrice($get, $set);
                        }),
                    Hidden::make('price_input_mode')->default('total'),
                    TextInput::make('sale_price')->label('مبلغ کل فروش (تومان)')->required(fn (Get $get): bool => $get('transaction_type') === 'sale')
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== 'sale')->numeric()->minValue(1)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                            $set('price_input_mode', 'total');
                            $area = (float) $get('area_sqm');
                            $set('sale_price_per_sqm', $area > 0 && (float) $state > 0
                                ? (string) round(((float) $state) / $area, 0, PHP_ROUND_HALF_UP) : null);
                        }),
                    TextInput::make('sale_price_per_sqm')->label('مبلغ هر متر (تومان)')->required(fn (Get $get): bool => $get('transaction_type') === 'sale')
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== 'sale')->numeric()->minValue(1)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                            $set('price_input_mode', 'per_sqm');
                            $area = (float) $get('area_sqm');
                            $set('sale_price', $area > 0 && (float) $state > 0
                                ? (string) round(((float) $state) * $area, 0, PHP_ROUND_HALF_UP) : null);
                        }),
                    TextInput::make('deposit_amount')
                        ->label(fn (Get $get): string => $get('transaction_type') === TransactionType::Sale->value
                            ? 'مبلغ ودیعه مستأجر (تومان)' : 'ودیعه اولیه (تومان)')
                        ->required(fn (Get $get): bool => $get('transaction_type') === TransactionType::Rent->value)
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value
                            && ! ($get('transaction_type') === TransactionType::Sale->value
                                && $get('delivery_status') === DeliveryStatus::TenantOccupied->value))
                        ->numeric()->minValue(0),
                    TextInput::make('monthly_rent')
                        ->label(fn (Get $get): string => $get('transaction_type') === TransactionType::Sale->value
                            ? 'مبلغ اجاره مستأجر (تومان)' : 'اجاره ماهانه (تومان)')
                        ->required(fn (Get $get): bool => $get('transaction_type') === TransactionType::Rent->value)
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value
                            && ! ($get('transaction_type') === TransactionType::Sale->value
                                && $get('delivery_status') === DeliveryStatus::TenantOccupied->value))
                        ->numeric()->minValue(0),
                    Toggle::make('is_convertible')->label('قابل تبدیل ودیعه و اجاره')->default(false)->live()
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== 'rent'),
                    TextInput::make('minimum_deposit')->label('حداقل ودیعه (تومان)')
                        ->required(fn (Get $get): bool => $get('transaction_type') === 'rent' && (bool) $get('is_convertible'))
                        ->hidden(fn (Get $get): bool => $get('transaction_type') !== 'rent' || ! (bool) $get('is_convertible'))
                        ->numeric()->minValue(0),
                    TextInput::make('street_address')->label('نشانی کامل')->required()->maxLength(500)->columnSpanFull(),
                    TextInput::make('plaque')->label('پلاک ساختمان')->required()->maxLength(32),
                    Select::make('delivery_status')->label('وضعیت تحویل')->required()
                        ->options(fn (Get $get): array => PersianLabels::options(
                            $get('transaction_type') === TransactionType::Sale->value
                                ? [
                                    DeliveryStatus::OwnerOccupied,
                                    DeliveryStatus::TenantOccupied,
                                    DeliveryStatus::Ready,
                                    DeliveryStatus::Vacated,
                                ]
                                : [
                                    DeliveryStatus::Ready,
                                    DeliveryStatus::Vacated,
                                    DeliveryStatus::Dated,
                                ],
                        ))->live(),
                    TextInput::make('evacuation_date_display')
                        ->label(fn (Get $get): string => $get('transaction_type') === TransactionType::Sale->value
                            && $get('delivery_status') === DeliveryStatus::Ready->value
                            ? 'تاریخ آماده تحویل'
                            : ($get('transaction_type') === TransactionType::Sale->value
                                && $get('delivery_status') === DeliveryStatus::TenantOccupied->value
                                ? 'تاریخ تخلیه مستأجر'
                                : 'تاریخ تخلیه'))
                        ->placeholder('۱۷ مرداد ۱۴۰۵')
                        ->helperText('روز، نام ماه و سال شمسی')
                        ->rules([new ClosureValidationRule(static function (string $attribute, mixed $value, Closure $fail): void {
                            if (blank($value)) {
                                return;
                            }
                            try {
                                PersianDate::parse((string) $value);
                            } catch (\InvalidArgumentException) {
                                $fail('تاریخ شمسی باید مانند «۱۷ مرداد ۱۴۰۵» وارد شود.');
                            }
                        })])
                        ->required(fn (Get $get): bool => ($get('transaction_type') === TransactionType::Sale->value
                                && in_array($get('delivery_status'), [
                                    DeliveryStatus::Ready->value,
                                    DeliveryStatus::TenantOccupied->value,
                                ], true))
                            || ($get('transaction_type') === TransactionType::Rent->value
                                && $get('delivery_status') === DeliveryStatus::Dated->value))
                        ->visible(fn (Get $get): bool => ($get('transaction_type') === TransactionType::Sale->value
                                && in_array($get('delivery_status'), [
                                    DeliveryStatus::Ready->value,
                                    DeliveryStatus::TenantOccupied->value,
                                ], true))
                            || ($get('transaction_type') === TransactionType::Rent->value
                                && $get('delivery_status') === DeliveryStatus::Dated->value)),
                ])->columns(2),
                Step::make('مشخصات و امکانات')->description('ویژگی‌های سازه‌ای و امکانات مشترک')->schema([
                    Section::make('مشخصات سازه‌ای')
                        ->hidden(fn (Get $get): bool => $get('property_type') === PropertyType::LandOldBuilding->value)
                        ->schema([
                            TextInput::make('bedrooms')->label('تعداد اتاق')->required()->numeric()->minValue(0),
                            TextInput::make('floor_number')->label('طبقه')->numeric(),
                            TextInput::make('total_floors')->label('تعداد طبقات')->numeric()->minValue(0),
                            TextInput::make('units_per_floor')->label('تعداد واحد در طبقه')->numeric()->minValue(0),
                            TextInput::make('year_built')->label('سال ساخت')->numeric()->minValue(1200)->maxValue(1500)
                                ->visible(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value)
                                ->dehydrated(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value),
                            TextInput::make('parking_spaces')->label('تعداد پارکینگ')->numeric()->minValue(0)->default(0),
                            Toggle::make('has_storage_room')->label('انباری'), Toggle::make('has_elevator')->label('آسانسور'),
                            Toggle::make('has_balcony')->label('بالکن'),
                        ])->columns(2),
                    Section::make('مشخصات صنعتی')->visible(fn (Get $get): bool => $get('property_type') === PropertyType::Industrial->value)
                        ->schema([
                            TextInput::make('structure_type')->label('نوع سازه'),
                            Toggle::make('has_water')->label('آب'),
                            Toggle::make('has_electricity')->label('برق'),
                            Toggle::make('has_gas')->label('گاز'),
                            TextInput::make('telephone_line_count')->label('تعداد خط تلفن'),
                            TextInput::make('land_area')->label('متراژ زمین'),
                            TextInput::make('building_area')->label('متراژ بنا'),
                        ])->columns(2),
                    Section::make('مشخصات زمین')->visible(fn (Get $get): bool => $get('property_type') === PropertyType::LandOldBuilding->value)
                        ->schema([
                            ToggleButtons::make('deed_type')->label('نوع سند')
                                ->options(PropertyFeatureOptions::DEED_TYPES)->inline()->grouped(),
                            Toggle::make('has_loan')->label('وام'),
                            Toggle::make('is_exchangeable')->label('قابل معاوضه'),
                            Toggle::make('can_aggregate')->label('قابلیت تجمیع'),
                            TextInput::make('land_frontage')->label('حد زمین (بر)')->suffix('متر')->maxLength(64),
                        ])->columns(2),
                    Section::make('امکانات مشترک')
                        ->hidden(fn (Get $get): bool => $get('property_type') === PropertyType::LandOldBuilding->value)
                        ->schema([
                            CheckboxList::make('toilet_types')->label('نوع سرویس')
                                ->options(PropertyFeatureOptions::TOILET_TYPES)->columns(2),
                            Toggle::make('has_master_bathroom')->label('سرویس مستر'),
                            Select::make('cabinet_type')->label('نوع کابینت')
                                ->options(PropertyFeatureOptions::CABINET_TYPES),
                            Select::make('heating_type')->label('نوع گرمایش')
                                ->options(PropertyFeatureOptions::HEATING_TYPES),
                            Select::make('cooling_type')->label('نوع سرمایش')
                                ->options(PropertyFeatureOptions::COOLING_TYPES),
                            Select::make('flooring_type')->label('نوع کف‌پوش')
                                ->options(PropertyFeatureOptions::FLOORING_TYPES),
                            ToggleButtons::make('renovation_status')->label('وضعیت بازسازی')
                                ->options(PropertyFeatureOptions::RENOVATION_STATUSES)->inline()->grouped(),
                            ToggleButtons::make('building_orientation')->label('جهت ساختمان')
                                ->options(PropertyFeatureOptions::ORIENTATIONS)->inline()->grouped(),
                            ToggleButtons::make('deed_type')->label('نوع سند')
                                ->options(PropertyFeatureOptions::DEED_TYPES)->inline()->grouped(),
                            Toggle::make('has_loan')->label('وام')
                                ->visible(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value)
                                ->dehydrated(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value),
                            Toggle::make('is_exchangeable')->label('قابل معاوضه')
                                ->visible(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value)
                                ->dehydrated(fn (Get $get): bool => $get('transaction_type') !== TransactionType::Rent->value),
                            Toggle::make('has_pool')->label('استخر'),
                            Toggle::make('has_jacuzzi')->label('جکوزی'),
                            Toggle::make('has_sauna')->label('سونا'),
                        ])->columns(2),
                ]),
                Step::make('مالک، تصاویر و توضیحات')->description('مرحله نهایی ثبت ملک')->schema([
                    Section::make('اطلاعات مالک')->statePath('owner')->schema([
                        TextInput::make('full_name')->label('نام کامل مالک')->required()->maxLength(200),
                        TextInput::make('mobile')->label('شماره همراه مالک')->required()->tel()->maxLength(32),
                        TextInput::make('phone')->label('تلفن ثابت مالک')->tel()->maxLength(32),
                        Textarea::make('notes')->label('توضیحات مالک')->maxLength(4000)->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),
                    FileUpload::make('new_images')->label('تصاویر ملک')->multiple()->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxFiles(5)->maxSize(10240)
                        ->storeFiles(false)->panelLayout('grid')
                        ->extraFieldWrapperAttributes(['class' => 'melkban-property-image-upload'])
                        ->helperText('اختیاری، حداکثر پنج تصویر؛ اولین تصویر، تصویر اصلی است.')
                        ->columnSpanFull(),
                    Textarea::make('description')->label('توضیحات ملک')->maxLength(20000)->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
        ];
    }

    /** @return list<mixed> */
    public static function customer(bool $agent): array
    {
        return [
            Wizard::make([
                Step::make('اطلاعات تماس')->schema([
                    TextInput::make('full_name')->label('نام و نام خانوادگی')->required()->maxLength(200),
                    TextInput::make('mobile')->label('شماره همراه')->required()->tel()->maxLength(32),
                    TextInput::make('phone')->label('تلفن ثابت')->tel()->maxLength(32),
                ])->columns(2),
                Step::make('نیاز مشتری')->schema([
                    Select::make('intent')->label('قصد مشتری')->required()->options(PersianLabels::options(CustomerIntent::cases()))->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            if ($state === CustomerIntent::Rent->value) {
                                $set('has_loan', false);
                                $set('is_exchangeable', false);
                                $set('has_pool', false);
                                $set('has_jacuzzi', false);
                                $set('has_sauna', false);
                                $set('deed_type', null);
                                $set('owner_resides', false);
                            } else {
                                $set('owner_resides', false);
                            }
                        }),
                    Select::make('desired_property_type')->label('نوع ملک موردنظر')->required()->options(self::propertyTypeOptions())->live()
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                            if ($state === PropertyType::LandOldBuilding->value) {
                                $set('intent', CustomerIntent::Buy->value);
                                $set('owner_resides', false);
                            }
                            if (! self::isHouseVilla($state)) {
                                $set('building_type', null);
                            }
                            if ($get('intent') === CustomerIntent::Rent->value
                                && $state !== PropertyType::Apartment->value) {
                                $set('has_master_bathroom', false);
                            }
                            if ($state !== PropertyType::Industrial->value) {
                                foreach (['structure_type', 'telephone_line_count', 'land_area', 'building_area'] as $field) {
                                    $set($field, null);
                                }
                                foreach (['has_water', 'has_electricity', 'has_gas'] as $field) {
                                    $set($field, false);
                                }
                            }
                        }),
                    Select::make('building_type')->label('نوع بنا')
                        ->options(PropertyFeatureOptions::BUILDING_TYPES)
                        ->required(fn (Get $get): bool => self::isHouseVilla($get('desired_property_type')))
                        ->visible(fn (Get $get): bool => self::isHouseVilla($get('desired_property_type'))),
                    TextInput::make('desired_city')->label('شهر موردنظر')->maxLength(100),
                    TextInput::make('desired_district')->label('محله موردنظر')->maxLength(100),
                    TextInput::make('min_area_sqm')->label('حداقل متراژ')->required()->numeric()->minValue(0.01),
                    TextInput::make('max_area_sqm')->label('حداکثر متراژ')->required()->numeric()->minValue(0.01),
                    TextInput::make('min_bedrooms')->label('حداقل تعداد اتاق')->numeric()->minValue(0)
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                ])->columns(2),
                Step::make('ویژگی‌های موردنظر')->schema([
                    Section::make('مشخصات اجاره‌ای')->visible(fn (Get $get): bool => $get('intent') === CustomerIntent::Rent->value)
                        ->schema([
                            TextInput::make('min_bedrooms')->label('حداقل تعداد اتاق')->numeric()->minValue(0)
                                ->dehydrated(fn (Get $get): bool => $get('intent') === CustomerIntent::Rent->value),
                            Toggle::make('has_elevator')->label('آسانسور')
                                ->dehydrated(fn (Get $get): bool => $get('intent') === CustomerIntent::Rent->value),
                            Toggle::make('has_balcony')->label('بالکن')
                                ->dehydrated(fn (Get $get): bool => $get('intent') === CustomerIntent::Rent->value),
                            Toggle::make('owner_resides')->label('مالک در ساختمان سکونت دارد')
                                ->dehydrated(fn (Get $get): bool => $get('intent') === CustomerIntent::Rent->value),
                        ])->columns(2),
                    Toggle::make('has_parking')->label('پارکینگ'),
                    Toggle::make('has_storage_room')->label('انباری'),
                    Section::make('مشخصات صنعتی')->visible(fn (Get $get): bool => $get('desired_property_type') === PropertyType::Industrial->value)
                        ->schema([
                            TextInput::make('structure_type')->label('نوع سازه'),
                            Toggle::make('has_water')->label('آب'),
                            Toggle::make('has_electricity')->label('برق'),
                            Toggle::make('has_gas')->label('گاز'),
                            TextInput::make('telephone_line_count')->label('تعداد خط تلفن'),
                            TextInput::make('land_area')->label('متراژ زمین'),
                            TextInput::make('building_area')->label('متراژ بنا'),
                        ])->columns(2),
                    CheckboxList::make('toilet_types')->label('نوع سرویس')
                        ->options(PropertyFeatureOptions::TOILET_TYPES)->columns(2)->columnSpanFull(),
                    Toggle::make('has_master_bathroom')->label('سرویس مستر')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value
                            || $get('desired_property_type') === PropertyType::Apartment->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value
                            || $get('desired_property_type') === PropertyType::Apartment->value),
                    Select::make('cabinet_type')->label('نوع کابینت')
                        ->options(PropertyFeatureOptions::CABINET_TYPES)->placeholder('انتخاب نشده'),
                    Select::make('heating_type')->label('نوع گرمایش')
                        ->options(PropertyFeatureOptions::HEATING_TYPES)->placeholder('انتخاب نشده'),
                    Select::make('cooling_type')->label('نوع سرمایش')
                        ->options(PropertyFeatureOptions::COOLING_TYPES)->placeholder('انتخاب نشده'),
                    Select::make('flooring_type')->label('نوع کف‌پوش')
                        ->options(PropertyFeatureOptions::FLOORING_TYPES)->placeholder('انتخاب نشده'),
                    ToggleButtons::make('renovation_status')->label('وضعیت بازسازی')
                        ->options(PropertyFeatureOptions::RENOVATION_STATUSES)->inline()->columnSpanFull(),
                    ToggleButtons::make('building_orientation')->label('جهت ساختمان')
                        ->options(PropertyFeatureOptions::ORIENTATIONS)->inline()->columnSpanFull(),
                    ToggleButtons::make('deed_type')->label('نوع سند')
                        ->options(PropertyFeatureOptions::DEED_TYPES)->inline()->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                    Toggle::make('has_loan')->label('وام')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                    Toggle::make('is_exchangeable')->label('قابل معاوضه')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                    Toggle::make('has_pool')->label('استخر')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                    Toggle::make('has_jacuzzi')->label('جکوزی')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                    Toggle::make('has_sauna')->label('سونا')
                        ->visible(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value)
                        ->dehydrated(fn (Get $get): bool => $get('intent') !== CustomerIntent::Rent->value),
                ])->columns(2),
                Step::make('بازه مبلغ')->schema([
                    TextInput::make('budget_min')->label('حداقل بودجه خرید (تومان)')->required(fn (Get $get): bool => $get('intent') === 'buy')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'buy')->numeric()->minValue(0),
                    TextInput::make('budget_max')->label('حداکثر بودجه خرید (تومان)')->required(fn (Get $get): bool => $get('intent') === 'buy')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'buy')->numeric()->minValue(0),
                    TextInput::make('rental_deposit_min')->label('حداقل ودیعه (تومان)')->required(fn (Get $get): bool => $get('intent') === 'rent')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'rent')->numeric()->minValue(0),
                    TextInput::make('rental_deposit_max')->label('حداکثر ودیعه (تومان)')->required(fn (Get $get): bool => $get('intent') === 'rent')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'rent')->numeric()->minValue(0),
                    TextInput::make('rental_rent_min')->label('حداقل اجاره ماهانه (تومان)')->required(fn (Get $get): bool => $get('intent') === 'rent')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'rent')->numeric()->minValue(0),
                    TextInput::make('rental_rent_max')->label('حداکثر اجاره ماهانه (تومان)')->required(fn (Get $get): bool => $get('intent') === 'rent')
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'rent')->numeric()->minValue(0),
                    Toggle::make('accepts_rent_conversion')->label('تبدیل ودیعه و اجاره پذیرفته می‌شود')->default(true)
                        ->hidden(fn (Get $get): bool => $get('intent') !== 'rent'),
                ])->columns(2),
                Step::make('توضیحات')->schema([
                    Textarea::make('description')->label('توضیحات مشتری')->maxLength(10000)->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
        ];
    }

    /** @return list<mixed> */
    public static function savedFilter(): array
    {
        return [
            Select::make('module')->label(PersianLabels::field('module'))->required()->options(PersianLabels::options(FilterModule::cases())),
            TextInput::make('name')->label(PersianLabels::field('name'))->required()->maxLength(100),
            KeyValue::make('filters')->label(PersianLabels::field('filters'))->keyLabel('فیلد')->valueLabel('مقدار')->addActionLabel('افزودن فیلتر')->required()->columnSpanFull(),
            TextInput::make('sort')->label(PersianLabels::field('sort'))->helperText('نام فیلد مرتب‌سازی را وارد کنید؛ برای ترتیب نزولی، ابتدای آن خط تیره بگذارید.'),
            Toggle::make('is_default')->label('پیش‌فرض این بخش'),
        ];
    }

    /** @return array<int, string> */
    private static function agentOptions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->agency_id === null) {
            return [];
        }

        return User::role(RoleName::Agent->value)->where('agency_id', $user->agency_id)
            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return list<string> */
    private static function buildingFields(): array
    {
        return [
            'bedrooms', 'bathrooms', 'master_bedrooms', 'floor_number',
            'total_floors', 'units_per_floor', 'year_built',
        ];
    }

    /** @return array<string, string> */
    private static function propertyTypeOptions(): array
    {
        return PersianLabels::options(PropertyType::selectable());
    }

    private static function isHouseVilla(mixed $type): bool
    {
        return in_array($type, [PropertyType::House->value, PropertyType::Villa->value, PropertyType::HouseVilla->value], true);
    }

    private static function recalculateSalePrice(Get $get, Set $set): void
    {
        if ($get('transaction_type') !== TransactionType::Sale->value) {
            return;
        }

        $area = (float) $get('area_sqm');
        if ($area <= 0) {
            return;
        }

        if ($get('price_input_mode') === 'per_sqm' && (float) $get('sale_price_per_sqm') > 0) {
            $set('sale_price', (string) round(((float) $get('sale_price_per_sqm')) * $area, 0, PHP_ROUND_HALF_UP));

            return;
        }

        if ((float) $get('sale_price') > 0) {
            $set('sale_price_per_sqm', (string) round(((float) $get('sale_price')) / $area, 0, PHP_ROUND_HALF_UP));
        }
    }
}
