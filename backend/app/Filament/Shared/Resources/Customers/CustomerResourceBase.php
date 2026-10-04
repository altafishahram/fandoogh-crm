<?php

declare(strict_types=1);

namespace App\Filament\Shared\Resources\Customers;

use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Customer\Services\CustomerNoteService;
use App\Application\Customer\Services\DeleteCustomerService;
use App\Application\Customer\Services\RestoreCustomerService;
use App\Application\Customer\Services\UpdateCustomerService;
use App\Application\Matching\Services\RelatedMatchQuery;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\User\Enums\RoleName;
use App\Filament\Shared\RelationManagers\CustomerHistoriesRelationManager;
use App\Filament\Shared\RelationManagers\CustomerNotesRelationManager;
use App\Filament\Shared\Support\LocationFields;
use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\RelatedMatchPresentation;
use App\Filament\Shared\Support\ResourceForms;
use App\Livewire\RelatedMatchList;
use App\Models\Customer;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;

abstract class CustomerResourceBase extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'مشتری';

    protected static ?string $pluralModelLabel = 'مشتریان';

    protected static ?string $navigationLabel = 'مشتریان';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::customer(static::actor()->roleName() === RoleName::Agent));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Livewire::make(RelatedMatchList::class, fn (Customer $record): array => [
                'side' => 'customer', 'recordId' => (int) $record->getKey(),
            ])->key(fn (Customer $record): string => 'customer-matches-'.$record->getKey())
                ->columnSpanFull(),
            Section::make('اطلاعات مشتری')->schema([
                TextEntry::make('full_name')->label('نام و نام خانوادگی'),
                TextEntry::make('mobile')->label('شماره همراه'), TextEntry::make('phone')->label('تلفن ثابت')->placeholder('—'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('intent')->label('قصد مشتری')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('desired_property_type')->label('نوع ملک موردنظر')->formatStateUsing(PersianLabels::value(...)),
                TextEntry::make('created_by_role')->label('ثبت‌کننده')->formatStateUsing(
                    fn (mixed $state): string => $state === RoleName::AgencyManager->value ? 'مدیر آژانس' : 'کارشناس',
                ),
                TextEntry::make('created_at')->label('تاریخ ثبت')->formatStateUsing(PersianDate::format(...)),
            ])->columns(4),
            Section::make('محدوده نیاز')->schema([
                TextEntry::make('desired_city')->label('شهر')->placeholder('—'), TextEntry::make('desired_district')->label('محله')->placeholder('—'),
                TextEntry::make('min_area_sqm')->label('حداقل متراژ'), TextEntry::make('max_area_sqm')->label('حداکثر متراژ'),
                TextEntry::make('budget_min')->label('حداقل بودجه')->numeric()->placeholder('—'), TextEntry::make('budget_max')->label('حداکثر بودجه')->numeric()->placeholder('—'),
                TextEntry::make('rental_deposit_min')->label('حداقل ودیعه')->numeric()->placeholder('—'), TextEntry::make('rental_deposit_max')->label('حداکثر ودیعه')->numeric()->placeholder('—'),
                TextEntry::make('rental_rent_min')->label('حداقل اجاره')->numeric()->placeholder('—'), TextEntry::make('rental_rent_max')->label('حداکثر اجاره')->numeric()->placeholder('—'),
            ])->columns(4),
            Section::make('ویژگی‌های موردنظر')->schema([
                TextEntry::make('min_bedrooms')->label('حداقل تعداد اتاق')->placeholder('—'),
                TextEntry::make('has_parking')->label('پارکینگ')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('has_storage_room')->label('انباری')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('owner_resides')->label('مالک در ساختمان سکونت دارد')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent === CustomerIntent::Rent),
                TextEntry::make('has_elevator')->label('آسانسور')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent === CustomerIntent::Rent),
                TextEntry::make('has_balcony')->label('بالکن')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent === CustomerIntent::Rent),
                TextEntry::make('toilet_types')->label('نوع سرویس')->formatStateUsing(
                    fn (mixed $state): string => is_array($state) && $state !== []
                        ? implode('، ', array_map(PersianLabels::value(...), $state)) : '—',
                ),
                TextEntry::make('has_master_bathroom')->label('سرویس مستر')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent
                        || $record->desired_property_type === PropertyType::Apartment->value),
                TextEntry::make('cabinet_type')->label('نوع کابینت')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('heating_type')->label('نوع گرمایش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('cooling_type')->label('نوع سرمایش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('flooring_type')->label('نوع کف‌پوش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('renovation_status')->label('وضعیت بازسازی')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('building_orientation')->label('جهت ساختمان')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('deed_type')->label('نوع سند')->formatStateUsing(PersianLabels::value(...))->placeholder('—')
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
                TextEntry::make('has_loan')->label('وام')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
                TextEntry::make('is_exchangeable')->label('قابل معاوضه')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
                TextEntry::make('has_pool')->label('استخر')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
                TextEntry::make('has_jacuzzi')->label('جکوزی')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
                TextEntry::make('has_sauna')->label('سونا')->formatStateUsing(self::yesNo(...))
                    ->visible(fn (Customer $record): bool => $record->intent !== CustomerIntent::Rent),
            ])->columns(4),
            Section::make('مشخصات خانه، ویلا و صنعتی')->schema([
                TextEntry::make('building_type')->label('نوع بنا')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('structure_type')->label('نوع سازه')->placeholder('—'),
                TextEntry::make('has_water')->label('آب')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('has_electricity')->label('برق')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('has_gas')->label('گاز')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('telephone_line_count')->label('تعداد خط تلفن')->placeholder('—'),
                TextEntry::make('land_area')->label('متراژ زمین')->placeholder('—'),
                TextEntry::make('building_area')->label('متراژ بنا')->placeholder('—'),
                TextEntry::make('land_area_min')->label('حداقل متراژ زمین')->numeric()->placeholder('—'),
                TextEntry::make('land_area_max')->label('حداکثر متراژ زمین')->numeric()->placeholder('—'),
                TextEntry::make('building_area_min')->label('حداقل متراژ بنا')->numeric()->placeholder('—'),
                TextEntry::make('building_area_max')->label('حداکثر متراژ بنا')->numeric()->placeholder('—'),
            ])->columns(4),
            Section::make('توضیحات')->schema([
                TextEntry::make('description')->label('توضیحات مشتری')->placeholder('توضیحی ثبت نشده است.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Stack::make([
                Split::make([
                    TextColumn::make('full_name')->label('نام مشتری')->searchable()->weight(FontWeight::Bold)->grow(),
                    TextColumn::make('status')->label('وضعیت')->formatStateUsing(PersianLabels::value(...))->badge(),
                ]),
                TextColumn::make('mobile')->label('شماره همراه')->searchable()->prefix('همراه: '),
                TextColumn::make('need')->label('نیاز')->state(fn (Customer $record): string => implode(' · ', array_filter([
                    PersianLabels::value($record->intent), PersianLabels::value($record->desired_property_type),
                    $record->desired_district,
                ]))),
                TextColumn::make('created_at')->label('تاریخ ثبت')->formatStateUsing(PersianDate::format(...))->color('gray'),
                TextColumn::make('match_count')->label('املاک منطبق')
                    ->formatStateUsing(fn (mixed $state, Customer $record): string => RelatedMatchPresentation::summary((int) $state, (int) $record->getAttribute('unread_match_count')))
                    ->tooltip(fn (Customer $record): string => RelatedMatchPresentation::accessibleSummary((int) $record->getAttribute('match_count'), (int) $record->getAttribute('unread_match_count')))
                    ->badge()->color(fn (Customer $record): string => (int) $record->getAttribute('unread_match_count') > 0 ? 'warning' : 'gray')
                    ->url(fn (Customer $record): string => static::getUrl('view', ['record' => $record]).'#related-matches'),
            ])->space(2),
        ])->contentGrid(['md' => 2, 'xl' => 3])->poll('30s')->filters([
            LocationFields::filter('desired_'),

            SelectFilter::make('status')->label('وضعیت')->multiple()->options(PersianLabels::options(CustomerStatus::cases())),
            SelectFilter::make('intent')->label('قصد مشتری')->options(PersianLabels::options(CustomerIntent::cases())),
            SelectFilter::make('desired_property_type')->label('نوع ملک')->multiple()->options(PersianLabels::options(PropertyType::cases())),
            Filter::make('advanced')->label('فیلتر پیشرفته')->schema([
                TextInput::make('desired_city')->label('شهر'), TextInput::make('desired_district')->label('محله'),
                TextInput::make('area_min')->label('حداقل متراژ')->numeric(), TextInput::make('area_max')->label('حداکثر متراژ')->numeric(),
                TextInput::make('amount_min')->label('حداقل مبلغ')->numeric(), TextInput::make('amount_max')->label('حداکثر مبلغ')->numeric(),
                Select::make('order')->label('مرتب‌سازی')->options(['newest' => 'جدیدترین', 'oldest' => 'قدیمی‌ترین'])->default('newest'),
            ])->columns(2)->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(filled($data['desired_city'] ?? null), fn (Builder $q): Builder => $q->where('desired_city', (string) $data['desired_city']))
                    ->when(filled($data['desired_district'] ?? null), fn (Builder $q): Builder => $q->where('desired_district', (string) $data['desired_district']))
                    ->when(filled($data['area_min'] ?? null), fn (Builder $q): Builder => $q->where('min_area_sqm', '>=', $data['area_min']))
                    ->when(filled($data['area_max'] ?? null), fn (Builder $q): Builder => $q->where('max_area_sqm', '<=', $data['area_max']))
                    ->when(filled($data['amount_min'] ?? null), fn (Builder $q): Builder => $q->where(fn (Builder $amount): Builder => $amount
                        ->where('budget_min', '>=', $data['amount_min'])->orWhere('rental_deposit_min', '>=', $data['amount_min'])))
                    ->when(filled($data['amount_max'] ?? null), fn (Builder $q): Builder => $q->where(fn (Builder $amount): Builder => $amount
                        ->where('budget_max', '<=', $data['amount_max'])->orWhere('rental_deposit_max', '<=', $data['amount_max'])))
                    ->when(($data['order'] ?? 'newest') === 'oldest', fn (Builder $q): Builder => $q->reorder('created_at')->orderBy('id'));
            }),
            TrashedFilter::make(),
        ])->recordActions([
            ViewAction::make(), EditAction::make(), self::statusAction(), self::noteAction(),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (Customer $record): bool => ! $record->trashed() && Gate::allows('delete', $record))
                ->action(function (Customer $record): void {
                    Gate::authorize('delete', $record);
                    app(DeleteCustomerService::class)->execute($record);
                }),
            Action::make('restore')->label('بازیابی')->color('success')->requiresConfirmation()
                ->visible(fn (Customer $record): bool => $record->trashed() && Gate::allows('restore', $record))
                ->action(function (Customer $record): void {
                    Gate::authorize('restore', $record);
                    app(RestoreCustomerService::class)->execute($record);
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [CustomerNotesRelationManager::class, CustomerHistoriesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(RelatedMatchQuery::class)->withSummaries(
            parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]),
            static::actor(),
            'customer',
        );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function statusAction(): Action
    {
        return Action::make('change_status')->label('تغییر وضعیت')->schema([
            Select::make('status')->label('وضعیت جدید')->required()->options(PersianLabels::options(CustomerStatus::cases())),
            Textarea::make('reason')->label('دلیل')->maxLength(1000),
        ])->visible(fn (Customer $record): bool => ! $record->trashed() && Gate::allows('changeStatus', $record))
            ->action(function (Customer $record, array $data): void {
                Gate::authorize('changeStatus', $record);
                app(UpdateCustomerService::class)->execute(static::actor(), $record, new UpdateCustomerData(
                    ['status' => CustomerStatus::from((string) $data['status'])],
                    CarbonImmutable::parse((string) $record->updated_at),
                    (int) $record->lock_version,
                    isset($data['reason']) ? (string) $data['reason'] : null,
                ));
            });
    }

    private static function noteAction(): Action
    {
        return Action::make('add_note')->label('افزودن یادداشت')->schema([
            Textarea::make('body')->label('متن یادداشت')->required()->maxLength(5000),
        ])->visible(fn (Customer $record): bool => ! $record->trashed() && Gate::allows('createNotes', $record))
            ->action(function (Customer $record, array $data): void {
                Gate::authorize('createNotes', $record);
                app(CustomerNoteService::class)->create($record, static::actor(), (string) $data['body']);
            });
    }

    protected static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private static function yesNo(mixed $state): string
    {
        return (bool) $state ? 'بله' : 'خیر';
    }
}
