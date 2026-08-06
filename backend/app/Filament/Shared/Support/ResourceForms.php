<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Owner\Services\CreateOwnerService;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\SavedFilter\Enums\FilterModule;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Gate;

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

        return [
            ...$fields,
            TextInput::make('name')->label(PersianLabels::field('name'))->required()->maxLength(160),
            TextInput::make('email')->label(PersianLabels::field('email'))->email()->required()->maxLength(255),
            TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->maxLength(30),
            TextInput::make('temporary_password')->label(PersianLabels::field('temporary_password'))->password()->revealable()->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
        ];
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
        $fields = [
            TextInput::make('title')->label(PersianLabels::field('title'))->required()->maxLength(200),
            Textarea::make('description')->label(PersianLabels::field('description'))->maxLength(10000)->columnSpanFull(),
            Select::make('property_type')->label(PersianLabels::field('property_type'))->required()->options(PersianLabels::options(PropertyType::cases())),
            Select::make('transaction_type')->label(PersianLabels::field('transaction_type'))->required()->options(PersianLabels::options(TransactionType::cases()))
                ->disabled(fn (string $operation): bool => $agent && $operation === 'edit'),
        ];
        if (! $agent) {
            $fields[] = Select::make('assigned_agent_id')->label(PersianLabels::field('assigned_agent_id'))->searchable()
                ->options(fn (): array => self::agentOptions());
        }

        return [
            ...$fields,
            TextInput::make('sale_price')->label(PersianLabels::field('sale_price'))->numeric()->minValue(0),
            TextInput::make('deposit_amount')->label(PersianLabels::field('deposit_amount'))->numeric()->minValue(0),
            TextInput::make('monthly_rent')->label(PersianLabels::field('monthly_rent'))->numeric()->minValue(0), TextInput::make('area_sqm')->label(PersianLabels::field('area_sqm'))->numeric()->minValue(0),
            TextInput::make('bedrooms')->label(PersianLabels::field('bedrooms'))->numeric()->minValue(0), TextInput::make('bathrooms')->label(PersianLabels::field('bathrooms'))->numeric()->minValue(0),
            TextInput::make('floor_number')->label(PersianLabels::field('floor_number'))->numeric(), TextInput::make('total_floors')->label(PersianLabels::field('total_floors'))->numeric()->minValue(0),
            TextInput::make('year_built')->label(PersianLabels::field('year_built'))->numeric()->minValue(1800)->maxValue(((int) date('Y')) + 1),
            TextInput::make('parking_spaces')->label(PersianLabels::field('parking_spaces'))->numeric()->minValue(0)->default(0)->required(),
            Toggle::make('has_storage_room')->label(PersianLabels::field('has_storage_room')), Toggle::make('has_elevator')->label(PersianLabels::field('has_elevator')), Toggle::make('has_balcony')->label(PersianLabels::field('has_balcony')),
            TextInput::make('city')->label(PersianLabels::field('city'))->required()->maxLength(100), TextInput::make('district')->label(PersianLabels::field('district'))->maxLength(100),
            TextInput::make('street_address')->label(PersianLabels::field('street_address'))->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('postal_code')->label(PersianLabels::field('postal_code'))->maxLength(20), TextInput::make('latitude')->label(PersianLabels::field('latitude'))->numeric(),
            TextInput::make('longitude')->label(PersianLabels::field('longitude'))->numeric(), DatePicker::make('available_from')->label(PersianLabels::field('available_from')),
            Repeater::make('owners')->label(PersianLabels::field('owners'))->required()->minItems(1)->columnSpanFull()->schema([
                Select::make('owner_id')->label(PersianLabels::field('owner_id'))->required()->searchable()->options(fn (): array => Owner::query()
                    ->orderBy('last_name')->orderBy('company_name')->get()->mapWithKeys(
                        fn (Owner $owner): array => [(int) $owner->getKey() => self::ownerLabel($owner)],
                    )->all())->createOptionForm(self::owner())->createOptionUsing(function (array $data): int {
                        Gate::authorize('create', Owner::class);
                        $owner = app(CreateOwnerService::class)->execute(self::actor(), PanelDataMapper::owner($data));

                        return (int) $owner->getKey();
                    }),
                TextInput::make('ownership_percentage')->label(PersianLabels::field('ownership_percentage'))->numeric()->minValue(0.01)->maxValue(100),
                Toggle::make('is_primary')->label(PersianLabels::field('is_primary')),
            ])->columns(3),
        ];
    }

    /** @return list<mixed> */
    public static function customer(bool $agent): array
    {
        $fields = [];
        if (! $agent) {
            $fields[] = Select::make('assigned_agent_id')->label(PersianLabels::field('assigned_agent_id'))->required()->searchable()->options(fn (): array => self::agentOptions());
        }

        return [
            ...$fields,
            TextInput::make('first_name')->label(PersianLabels::field('first_name'))->required()->maxLength(100), TextInput::make('last_name')->label(PersianLabels::field('last_name'))->required()->maxLength(100),
            TextInput::make('mobile')->label(PersianLabels::field('mobile'))->required()->tel()->maxLength(30), TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->maxLength(30),
            TextInput::make('email')->label(PersianLabels::field('email'))->email()->maxLength(255),
            Select::make('preferred_contact_method')->label(PersianLabels::field('preferred_contact_method'))->required()->options(PersianLabels::options(PreferredContactMethod::cases())),
            Select::make('intent')->label(PersianLabels::field('intent'))->required()->options(PersianLabels::options(CustomerIntent::cases())),
            Select::make('status')->label(PersianLabels::field('status'))->options(PersianLabels::options(CustomerStatus::cases()))
                ->visible(fn (string $operation): bool => $operation === 'edit'),
            Select::make('preferred_property_types')->label(PersianLabels::field('preferred_property_types'))->multiple()->options(PersianLabels::options(PropertyType::cases())),
            TextInput::make('budget_min')->label(PersianLabels::field('budget_min'))->numeric()->minValue(0), TextInput::make('budget_max')->label(PersianLabels::field('budget_max'))->numeric()->minValue(0),
            TextInput::make('desired_city')->label(PersianLabels::field('desired_city'))->maxLength(100), TextInput::make('desired_district')->label(PersianLabels::field('desired_district'))->maxLength(100),
            TextInput::make('min_area_sqm')->label(PersianLabels::field('min_area_sqm'))->numeric()->minValue(0), TextInput::make('max_area_sqm')->label(PersianLabels::field('max_area_sqm'))->numeric()->minValue(0),
            TextInput::make('min_bedrooms')->label(PersianLabels::field('min_bedrooms'))->numeric()->minValue(0),
            Select::make('converted_property_id')->label(PersianLabels::field('converted_property_id'))->searchable()->options(fn (): array => Property::query()
                ->whereIn('status', [PropertyStatus::Sold->value, PropertyStatus::Rented->value])
                ->orderBy('code')->pluck('title', 'id')->all())
                ->visible(fn (string $operation): bool => $operation === 'edit'),
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

    private static function ownerLabel(Owner $owner): string
    {
        return trim((string) ($owner->company_name ?: $owner->first_name.' '.$owner->last_name));
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
