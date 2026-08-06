<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\Properties;

use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\PropertyImageService;
use App\Application\Property\Services\PropertyNoteService;
use App\Domain\Property\Enums\PropertyStatus;
use App\Filament\Agent\Resources\Properties\Pages\CreateProperty;
use App\Filament\Agent\Resources\Properties\Pages\EditProperty;
use App\Filament\Agent\Resources\Properties\Pages\ListProperties;
use App\Filament\Agent\Resources\Properties\Pages\ViewProperty;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $modelLabel = 'ملک';

    protected static ?string $pluralModelLabel = 'املاک من';

    protected static ?string $navigationLabel = 'املاک من';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::property(true))->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code')->label(PersianLabels::field('code')), TextEntry::make('title')->label(PersianLabels::field('title')), TextEntry::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextEntry::make('property_type')->label(PersianLabels::field('property_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextEntry::make('transaction_type')->label(PersianLabels::field('transaction_type'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextEntry::make('city')->label(PersianLabels::field('city')), TextEntry::make('district')->label(PersianLabels::field('district'))->placeholder('-'),
            TextEntry::make('street_address')->label(PersianLabels::field('street_address'))->columnSpanFull(), TextEntry::make('sale_price')->label(PersianLabels::field('sale_price'))->numeric()->placeholder('-'),
            TextEntry::make('deposit_amount')->label(PersianLabels::field('deposit_amount'))->numeric()->placeholder('-'),
            TextEntry::make('monthly_rent')->label(PersianLabels::field('monthly_rent'))->numeric()->placeholder('-'), TextEntry::make('area_sqm')->label(PersianLabels::field('area_sqm'))->numeric()->placeholder('-'),
            TextEntry::make('description')->label(PersianLabels::field('description'))->placeholder('-')->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(PersianLabels::field('code'))->searchable()->sortable(), TextColumn::make('title')->label(PersianLabels::field('title'))->searchable()->sortable(),
            TextColumn::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge()->sortable(), TextColumn::make('property_type')->label(PersianLabels::field('property_type'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextColumn::make('transaction_type')->label(PersianLabels::field('transaction_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextColumn::make('city')->label(PersianLabels::field('city'))->searchable(),
            TextColumn::make('area_sqm')->label(PersianLabels::field('area_sqm'))->numeric()->sortable(), TextColumn::make('updated_at')->label(PersianLabels::field('updated_at'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->label(PersianLabels::field('status'))->options(self::statusOptions()),
            SelectFilter::make('property_type')->label(PersianLabels::field('property_type'))->options([
                'apartment' => 'آپارتمان', 'house' => 'خانه', 'villa' => 'ویلا', 'land' => 'زمین',
                'office' => 'اداری', 'commercial' => 'تجاری', 'warehouse' => 'انبار', 'other' => 'سایر',
            ]),
            SelectFilter::make('transaction_type')->label(PersianLabels::field('transaction_type'))->options(['sale' => 'فروش', 'rent' => 'اجاره']),
        ])->recordActions([
            ViewAction::make(), EditAction::make(),
            Action::make('change_status')->label('تغییر وضعیت')->schema([
                Select::make('status')->label(PersianLabels::field('status'))->required()->options(self::statusOptions()), Textarea::make('reason')->label(PersianLabels::field('reason'))->maxLength(1000),
            ])->visible(fn (Property $record): bool => Gate::allows('changeStatus', $record))
                ->action(function (Property $record, array $data): void {
                    Gate::authorize('changeStatus', $record);
                    app(ChangePropertyStatusService::class)->execute(self::actor(), $record, new ChangePropertyStatusData(
                        PropertyStatus::from((string) $data['status']), isset($data['reason']) ? (string) $data['reason'] : null,
                        null, CarbonImmutable::parse((string) $record->updated_at),
                    ));
                }),
            Action::make('add_note')->label('افزودن یادداشت')->schema([Textarea::make('body')->label(PersianLabels::field('body'))->required()->maxLength(5000)])
                ->visible(fn (Property $record): bool => Gate::allows('manageNotes', $record))
                ->action(function (Property $record, array $data): void {
                    Gate::authorize('manageNotes', $record);
                    app(PropertyNoteService::class)->create($record, self::actor(), (string) $data['body']);
                }),
            Action::make('upload_image')->label('بارگذاری تصویر')->schema([
                FileUpload::make('image')->label('تصویر ملک')->required()->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)->storeFiles(false),
            ])->visible(fn (Property $record): bool => Gate::allows('manageImages', $record))
                ->action(function (Property $record, array $data): void {
                    Gate::authorize('manageImages', $record);
                    $file = $data['image'] ?? null;
                    abort_unless($file instanceof UploadedFile, 422);
                    app(PropertyImageService::class)->upload($record, self::actor(), $file);
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
        return parent::getEloquentQuery()->where('assigned_agent_id', self::actor()->getKey());
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
