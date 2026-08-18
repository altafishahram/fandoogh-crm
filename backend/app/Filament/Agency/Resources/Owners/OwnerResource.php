<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Owners;

use App\Application\Owner\Services\DeleteOwnerService;
use App\Application\Owner\Services\RestoreOwnerService;
use App\Filament\Agency\Resources\Owners\Pages\CreateOwner;
use App\Filament\Agency\Resources\Owners\Pages\EditOwner;
use App\Filament\Agency\Resources\Owners\Pages\ListOwners;
use App\Filament\Agency\Resources\Owners\Pages\ViewOwner;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
use App\Models\Owner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Illuminate\Support\Facades\Gate;

final class OwnerResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Owner::class;

    protected static ?string $modelLabel = 'مالک';

    protected static ?string $pluralModelLabel = 'مالکان';

    protected static ?string $navigationLabel = 'مالکان';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::owner())->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('owner_type')->label(PersianLabels::field('owner_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextEntry::make('first_name')->label(PersianLabels::field('first_name'))->placeholder('-'),
            TextEntry::make('last_name')->label(PersianLabels::field('last_name'))->placeholder('-'), TextEntry::make('company_name')->label(PersianLabels::field('company_name'))->placeholder('-'),
            TextEntry::make('mobile')->label(PersianLabels::field('mobile'))->placeholder('-'), TextEntry::make('phone')->label(PersianLabels::field('phone'))->placeholder('-'),
            TextEntry::make('email')->label(PersianLabels::field('email'))->placeholder('-'), TextEntry::make('identity_number_encrypted')->label(PersianLabels::field('identity_number_encrypted'))->placeholder('-'),
            TextEntry::make('city')->label(PersianLabels::field('city'))->placeholder('-'), TextEntry::make('province')->label(PersianLabels::field('province'))->placeholder('-'),
            TextEntry::make('notes')->label(PersianLabels::field('notes'))->placeholder('-')->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('owner_type')->label(PersianLabels::field('owner_type'))->formatStateUsing(PersianLabels::value(...))->badge(), TextColumn::make('first_name')->label(PersianLabels::field('first_name'))->searchable()->sortable(),
            TextColumn::make('last_name')->label(PersianLabels::field('last_name'))->searchable()->sortable(), TextColumn::make('company_name')->label(PersianLabels::field('company_name'))->searchable()->sortable(),
            TextColumn::make('mobile')->label(PersianLabels::field('mobile'))->searchable(), TextColumn::make('email')->label(PersianLabels::field('email'))->searchable(),
            TextColumn::make('city')->label(PersianLabels::field('city'))->searchable(), TextColumn::make('properties_count')->counts('properties')->label(PersianLabels::field('properties_count')),
        ])->filters([
            SelectFilter::make('owner_type')->label(PersianLabels::field('owner_type'))->options(['person' => 'شخص', 'company' => 'شرکت']),
            TrashedFilter::make(),
        ])->recordActions([
            ViewAction::make(), EditAction::make(),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (Owner $record): bool => ! $record->trashed() && Gate::allows('delete', $record))
                ->action(function (Owner $record): void {
                    Gate::authorize('delete', $record);
                    app(DeleteOwnerService::class)->execute($record);
                }),
            Action::make('restore')->label('بازیابی')->color('success')->requiresConfirmation()
                ->visible(fn (Owner $record): bool => $record->trashed() && Gate::allows('restore', $record))
                ->action(function (Owner $record): void {
                    Gate::authorize('restore', $record);
                    app(RestoreOwnerService::class)->execute($record);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOwners::route('/'), 'create' => CreateOwner::route('/create'),
            'view' => ViewOwner::route('/{record}'), 'edit' => EditOwner::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
