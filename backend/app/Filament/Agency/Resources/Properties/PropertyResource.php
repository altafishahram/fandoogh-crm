<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Properties;

use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\DeletePropertyService;
use App\Application\Property\Services\PropertyImageService;
use App\Application\Property\Services\PropertyNoteService;
use App\Application\Property\Services\RestorePropertyService;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\Properties\Pages\CreateProperty;
use App\Filament\Agency\Resources\Properties\Pages\EditProperty;
use App\Filament\Agency\Resources\Properties\Pages\ListProperties;
use App\Filament\Agency\Resources\Properties\Pages\ViewProperty;
use App\Filament\Shared\RelationManagers\PropertyHistoriesRelationManager;
use App\Filament\Shared\RelationManagers\PropertyImagesRelationManager;
use App\Filament\Shared\RelationManagers\PropertyNotesRelationManager;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $modelLabel = 'ملک';

    protected static ?string $pluralModelLabel = 'املاک';

    protected static ?string $navigationLabel = 'املاک';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::property(false))->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code')->label(PersianLabels::field('code')), TextEntry::make('title')->label(PersianLabels::field('title')), TextEntry::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextEntry::make('property_type')->label(PersianLabels::field('property_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextEntry::make('transaction_type')->label(PersianLabels::field('transaction_type'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextEntry::make('assignedAgent.name')->label(PersianLabels::field('assignedAgent.name'))->placeholder('-'), TextEntry::make('city')->label(PersianLabels::field('city')),
            TextEntry::make('district')->label(PersianLabels::field('district'))->placeholder('-'), TextEntry::make('street_address')->label(PersianLabels::field('street_address'))->columnSpanFull(),
            TextEntry::make('sale_price')->label(PersianLabels::field('sale_price'))->numeric()->placeholder('-'),
            TextEntry::make('deposit_amount')->label(PersianLabels::field('deposit_amount'))->numeric()->placeholder('-'),
            TextEntry::make('monthly_rent')->label(PersianLabels::field('monthly_rent'))->numeric()->placeholder('-'),
            TextEntry::make('area_sqm')->label(PersianLabels::field('area_sqm'))->numeric()->placeholder('-'),
            TextEntry::make('owners')->label(PersianLabels::field('owners'))->formatStateUsing(fn (Property $record): string => $record->owners
                ->map(fn ($owner): string => (string) ($owner->company_name ?: $owner->first_name.' '.$owner->last_name))
                ->implode(', '))->columnSpanFull(),
            TextEntry::make('description')->label(PersianLabels::field('description'))->placeholder('-')->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(PersianLabels::field('code'))->searchable()->sortable(), TextColumn::make('title')->label(PersianLabels::field('title'))->searchable()->sortable(),
            TextColumn::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge()->sortable(), TextColumn::make('property_type')->label(PersianLabels::field('property_type'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextColumn::make('transaction_type')->label(PersianLabels::field('transaction_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextColumn::make('assignedAgent.name')->label(PersianLabels::field('assignedAgent.name'))->searchable(),
            TextColumn::make('city')->label(PersianLabels::field('city'))->searchable(), TextColumn::make('area_sqm')->label(PersianLabels::field('area_sqm'))->numeric()->sortable(),
            TextColumn::make('updated_at')->label(PersianLabels::field('updated_at'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->label(PersianLabels::field('status'))->options(self::statusOptions()),
            SelectFilter::make('property_type')->label(PersianLabels::field('property_type'))->options([
                'apartment' => 'آپارتمان', 'house' => 'خانه', 'villa' => 'ویلا', 'land' => 'زمین',
                'office' => 'اداری', 'commercial' => 'تجاری', 'warehouse' => 'انبار', 'other' => 'سایر',
            ]),
            SelectFilter::make('transaction_type')->label(PersianLabels::field('transaction_type'))->options(['sale' => 'فروش', 'rent' => 'اجاره']),
            SelectFilter::make('assigned_agent_id')->label(PersianLabels::field('assigned_agent_id'))->options(fn (): array => User::query()
                ->where('agency_id', self::actor()->agency_id)->where('is_active', true)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::Agent->value))
                ->orderBy('name')->pluck('name', 'id')->all()),
            TrashedFilter::make(),
        ])->recordActions([
            ViewAction::make(), EditAction::make(), self::statusAction(), self::noteAction(), self::imageAction(),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('delete', $record))
                ->action(function (Property $record): void {
                    Gate::authorize('delete', $record);
                    app(DeletePropertyService::class)->execute(self::actor(), $record);
                }),
            Action::make('restore')->label('بازیابی')->color('success')->requiresConfirmation()
                ->visible(fn (Property $record): bool => $record->trashed() && Gate::allows('restore', $record))
                ->action(function (Property $record): void {
                    Gate::authorize('restore', $record);
                    app(RestorePropertyService::class)->execute(self::actor(), $record);
                }),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'), 'create' => CreateProperty::route('/create'),
            'view' => ViewProperty::route('/{record}'), 'edit' => EditProperty::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [PropertyImagesRelationManager::class, PropertyNotesRelationManager::class, PropertyHistoriesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function statusAction(): Action
    {
        return Action::make('change_status')->label('تغییر وضعیت')->schema([
            Select::make('status')->label(PersianLabels::field('status'))->required()->options(self::statusOptions()),
            Textarea::make('reason')->label(PersianLabels::field('reason'))->maxLength(1000),
        ])->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('changeStatus', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('changeStatus', $record);
                app(ChangePropertyStatusService::class)->execute(self::actor(), $record, new ChangePropertyStatusData(
                    PropertyStatus::from((string) $data['status']), isset($data['reason']) ? (string) $data['reason'] : null,
                    null, CarbonImmutable::parse((string) $record->updated_at),
                ));
            });
    }

    private static function noteAction(): Action
    {
        return Action::make('add_note')->label('افزودن یادداشت')->schema([Textarea::make('body')->label(PersianLabels::field('body'))->required()->minLength(1)->maxLength(5000)])
            ->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('manageNotes', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('manageNotes', $record);
                app(PropertyNoteService::class)->create($record, self::actor(), (string) $data['body']);
            });
    }

    private static function imageAction(): Action
    {
        return Action::make('upload_image')->label('بارگذاری تصویر')->schema([
            FileUpload::make('image')->label('تصویر ملک')->required()->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(10240)->storeFiles(false),
        ])->visible(fn (Property $record): bool => ! $record->trashed() && Gate::allows('manageImages', $record))
            ->action(function (Property $record, array $data): void {
                Gate::authorize('manageImages', $record);
                $file = $data['image'] ?? null;
                abort_unless($file instanceof UploadedFile, 422);
                app(PropertyImageService::class)->upload($record, self::actor(), $file);
            });
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return PersianLabels::options(PropertyStatus::cases());
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
