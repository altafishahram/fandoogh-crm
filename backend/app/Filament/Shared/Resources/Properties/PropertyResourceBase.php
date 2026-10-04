<?php

declare(strict_types=1);

namespace App\Filament\Shared\Resources\Properties;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\DeletePropertyService;
use App\Application\Property\Services\PropertyImageService;
use App\Application\Property\Services\PropertyNoteService;
use App\Application\Property\Services\RestorePropertyService;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\User\Enums\RoleName;
use App\Filament\Shared\RelationManagers\PropertyHistoriesRelationManager;
use App\Filament\Shared\RelationManagers\PropertyImagesRelationManager;
use App\Filament\Shared\RelationManagers\PropertyNotesRelationManager;
use App\Filament\Shared\Support\LocationFields;
use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\PublicationAction;
use App\Filament\Shared\Support\RelatedMatchPresentation;
use App\Filament\Shared\Support\ResourceForms;
use App\Livewire\RelatedMatchList;
use App\Models\Property;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

abstract class PropertyResourceBase extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $modelLabel = 'ملک';

    protected static ?string $pluralModelLabel = 'املاک';

    protected static ?string $navigationLabel = 'املاک';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::property(static::actor()->roleName() === RoleName::Agent));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Livewire::make(RelatedMatchList::class, fn (Property $record): array => [
                'side' => 'property', 'recordId' => (int) $record->getKey(),
            ])->key(fn (Property $record): string => 'property-matches-'.$record->getKey())
                ->columnSpanFull(),
            Section::make('مشخصات ملک')->schema([
                TextEntry::make('code')->label('کد ملک'),
                TextEntry::make('title')->label('عنوان ملک'),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('transaction_type')->label('نوع معامله')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('property_type')->label('نوع ملک')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('area_sqm')->label('مساحت')->suffix(' متر مربع'),
                TextEntry::make('created_by_role')->label('ثبت‌کننده')->formatStateUsing(
                    fn (mixed $state): string => $state === RoleName::AgencyManager->value ? 'مدیر آژانس' : 'کارشناس',
                ),
                TextEntry::make('created_at')->label('تاریخ ثبت')->formatStateUsing(PersianDate::format(...)),
            ])->columns(4),
            Section::make('مبلغ‌ها')->schema([
                TextEntry::make('sale_price')->label('مبلغ کل فروش')->numeric(decimalPlaces: 0)->suffix(' تومان')->placeholder('—'),
                TextEntry::make('deposit_amount')->label('ودیعه اولیه')->numeric(decimalPlaces: 0)->suffix(' تومان')->placeholder('—'),
                TextEntry::make('monthly_rent')->label('اجاره ماهانه')->numeric(decimalPlaces: 0)->suffix(' تومان')->placeholder('—'),
                TextEntry::make('minimum_deposit')->label('حداقل ودیعه')->numeric(decimalPlaces: 0)->suffix(' تومان')->placeholder('—'),
            ])->columns(4),
            Section::make('نشانی و تحویل')->schema([
                TextEntry::make('city')->label('شهر')->placeholder('—'), TextEntry::make('district')->label('محله'),
                TextEntry::make('street_address')->label('نشانی کامل')->columnSpanFull(), TextEntry::make('plaque')->label('پلاک ساختمان'),
                TextEntry::make('delivery_status')->label('وضعیت تحویل')->formatStateUsing(PersianLabels::value(...))->badge(),
                TextEntry::make('evacuation_date')->label('تاریخ تخلیه')->formatStateUsing(PersianDate::format(...))->placeholder('—'),
                TextEntry::make('available_from')->label('تاریخ آماده تحویل')->formatStateUsing(PersianDate::format(...))->placeholder('—'),
            ])->columns(3),
            Section::make('امکانات و وضعیت بنا')->schema([
                TextEntry::make('toilet_types')->label('نوع سرویس')->formatStateUsing(PersianLabels::data(...))->separator('، ')->placeholder('—'),
                TextEntry::make('has_master_bathroom')->label('سرویس مستر')->formatStateUsing(fn (bool $state): string => $state ? 'دارد' : 'ندارد'),
                TextEntry::make('cabinet_type')->label('نوع کابینت')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('heating_type')->label('نوع گرمایش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('cooling_type')->label('نوع سرمایش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('flooring_type')->label('نوع کف‌پوش')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('renovation_status')->label('وضعیت بازسازی')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('building_orientation')->label('جهت ساختمان')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('deed_type')->label('نوع سند')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('features_summary')->label('سایر امکانات')->state(fn (Property $record): string => implode('، ', array_filter([
                    $record->has_loan ? 'وام' : null, $record->is_exchangeable ? 'قابل معاوضه' : null,
                    $record->has_pool ? 'استخر' : null, $record->has_jacuzzi ? 'جکوزی' : null,
                    $record->has_sauna ? 'سونا' : null,
                ])) ?: '—')->columnSpanFull(),
            ])->columns(3),
            Section::make('مشخصات خانه، ویلا و صنعتی')->schema([
                TextEntry::make('building_type')->label('نوع بنا')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
                TextEntry::make('structure_type')->label('نوع سازه')->placeholder('—'),
                TextEntry::make('has_water')->label('آب')->formatStateUsing(fn (bool $state): string => $state ? 'دارد' : 'ندارد'),
                TextEntry::make('has_electricity')->label('برق')->formatStateUsing(fn (bool $state): string => $state ? 'دارد' : 'ندارد'),
                TextEntry::make('has_gas')->label('گاز')->formatStateUsing(fn (bool $state): string => $state ? 'دارد' : 'ندارد'),
                TextEntry::make('telephone_line_count')->label('تعداد خط تلفن')->placeholder('—'),
                TextEntry::make('land_area')->label('متراژ زمین')->placeholder('—'),
                TextEntry::make('building_area')->label('متراژ بنا')->placeholder('—'),
                TextEntry::make('can_aggregate')->label('قابلیت تجمیع')->formatStateUsing(self::yesNo(...)),
                TextEntry::make('land_frontage')->label('حد زمین (بر)')->suffix(' متر')->placeholder('—'),
            ])->columns(4),
            Section::make('مالک')->schema([
                TextEntry::make('owners.full_name')->label('نام کامل مالک'),
                TextEntry::make('owners.mobile')->label('شماره همراه'),
                TextEntry::make('owners.phone')->label('تلفن ثابت')->placeholder('—'),
                TextEntry::make('owners.notes')->label('توضیحات مالک')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            Section::make('توضیحات')->schema([
                TextEntry::make('description')->label('توضیحات ملک')->placeholder('توضیحی ثبت نشده است.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('title')->label('عنوان ملک')->searchable()->weight(FontWeight::Bold)->grow(),
                        TextColumn::make('status')->label('وضعیت')->formatStateUsing(PersianLabels::value(...))->badge(),
                    ]),
                    TextColumn::make('code')->label('کد ملک')->searchable()->color('gray')->prefix('کد: '),
                    TextColumn::make('owners.full_name')->label('مالک')->searchable()->prefix('مالک: ')->limit(35),
                    TextColumn::make('summary')->label('خلاصه')->state(fn (Property $record): string => implode(' · ', array_filter([
                        PersianLabels::value($record->transaction_type), PersianLabels::value($record->property_type),
                        $record->district, $record->area_sqm === null ? null : $record->area_sqm.' متر',
                    ]))),
                    TextColumn::make('created_at')->label('تاریخ ثبت')->formatStateUsing(PersianDate::format(...))->color('gray'),
                    TextColumn::make('match_count')->label('مشتریان منطبق')
                        ->formatStateUsing(fn (mixed $state, Property $record): string => RelatedMatchPresentation::summary((int) $state, (int) $record->getAttribute('unread_match_count')))
                        ->tooltip(fn (Property $record): string => RelatedMatchPresentation::accessibleSummary((int) $record->getAttribute('match_count'), (int) $record->getAttribute('unread_match_count')))
                        ->badge()->color(fn (Property $record): string => (int) $record->getAttribute('unread_match_count') > 0 ? 'warning' : 'gray')
                        ->url(fn (Property $record): string => static::getUrl('view', ['record' => $record]).'#related-matches'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->poll('30s')
            ->filters([
                LocationFields::filter(),

                SelectFilter::make('status')->label('وضعیت')->multiple()->options(PersianLabels::options(PropertyStatus::cases())),
                SelectFilter::make('property_type')->label('نوع ملک')->multiple()->options(PersianLabels::options(PropertyType::selectable())),
                SelectFilter::make('transaction_type')->label('نوع معامله')->options(PersianLabels::options(TransactionType::cases())),
                Filter::make('advanced')->label('فیلتر پیشرفته')->schema([
                    TextInput::make('district')->label('محله'),
                    TextInput::make('area_min')->label('حداقل متراژ')->numeric(), TextInput::make('area_max')->label('حداکثر متراژ')->numeric(),
                    TextInput::make('price_min')->label('حداقل مبلغ')->numeric(), TextInput::make('price_max')->label('حداکثر مبلغ')->numeric(),
                    TextInput::make('bedrooms_min')->label('حداقل اتاق')->numeric(),
                    Select::make('order')->label('مرتب‌سازی')->options(['newest' => 'جدیدترین', 'oldest' => 'قدیمی‌ترین'])->default('newest'),
                ])->columns(2)->query(function (Builder $query, array $data): Builder {
                    $priceColumn = request()->input('tableFilters.transaction_type.value') === 'rent' ? 'deposit_amount' : 'sale_price';

                    return $query
                        ->when(filled($data['district'] ?? null), fn (Builder $q): Builder => $q->where('district', (string) $data['district']))
                        ->when(filled($data['area_min'] ?? null), fn (Builder $q): Builder => $q->where('area_sqm', '>=', $data['area_min']))
                        ->when(filled($data['area_max'] ?? null), fn (Builder $q): Builder => $q->where('area_sqm', '<=', $data['area_max']))
                        ->when(filled($data['price_min'] ?? null), fn (Builder $q): Builder => $q->where($priceColumn, '>=', $data['price_min']))
                        ->when(filled($data['price_max'] ?? null), fn (Builder $q): Builder => $q->where($priceColumn, '<=', $data['price_max']))
                        ->when(filled($data['bedrooms_min'] ?? null), fn (Builder $q): Builder => $q->where('bedrooms', '>=', $data['bedrooms_min']))
                        ->when(($data['order'] ?? 'newest') === 'oldest', fn (Builder $q): Builder => $q->reorder('created_at')->orderBy('id'));
                }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(), EditAction::make(), PublicationAction::make(), self::statusAction(), self::noteAction(), self::imageAction(),
                Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                    ->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('delete', $record))
                    ->action(function (Property $record): void {
                        Gate::authorize('delete', $record);
                        app(DeletePropertyService::class)->execute(static::actor(), $record);
                    }),
                Action::make('restore')->label('بازیابی')->color('success')->requiresConfirmation()
                    ->visible(fn (Property $record): bool => $record->trashed() && Gate::allows('restore', $record))
                    ->action(function (Property $record): void {
                        Gate::authorize('restore', $record);
                        app(RestorePropertyService::class)->execute(static::actor(), $record);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [PropertyImagesRelationManager::class, PropertyNotesRelationManager::class, PropertyHistoriesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(RelatedMatchQuery::class)->withSummaries(
            parent::getEloquentQuery()->with('owners')->withoutGlobalScopes([SoftDeletingScope::class]),
            static::actor(),
            'property',
        );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function statusAction(): Action
    {
        return Action::make('change_status')->label('تغییر وضعیت')->schema([
            Select::make('status')->label('وضعیت جدید')->required()->options(PersianLabels::options(PropertyStatus::cases())),
            Textarea::make('reason')->label('دلیل')->maxLength(1000),
        ])->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('changeStatus', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('changeStatus', $record);
                app(ChangePropertyStatusService::class)->execute(static::actor(), $record, new ChangePropertyStatusData(
                    PropertyStatus::from((string) $data['status']), isset($data['reason']) ? (string) $data['reason'] : null,
                    null, CarbonImmutable::parse((string) $record->updated_at), (int) $record->lock_version,
                ));
            });
    }

    private static function noteAction(): Action
    {
        return Action::make('add_note')->label('افزودن یادداشت')->schema([
            Textarea::make('body')->label('متن یادداشت')->required()->maxLength(5000),
        ])->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('createNotes', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('createNotes', $record);
                app(PropertyNoteService::class)->create($record, static::actor(), (string) $data['body']);
            });
    }

    private static function imageAction(): Action
    {
        return Action::make('upload_image')->label('افزودن تصویر')->schema([
            FileUpload::make('image')->label('تصویر ملک')->required()->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->storeFiles(false),
        ])->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('createImages', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('createImages', $record);
                $file = $data['image'] ?? null;
                abort_unless($file instanceof UploadedFile, 422);
                app(PropertyImageService::class)->upload($record, static::actor(), $file);
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
        return (bool) $state ? 'دارد' : 'ندارد';
    }
}
