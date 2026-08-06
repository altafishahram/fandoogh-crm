<?php

declare(strict_types=1);

namespace App\Filament\Agent\Resources\SavedFilters;

use App\Application\SavedFilter\Services\SavedFilterService;
use App\Filament\Agent\Resources\SavedFilters\Pages\CreateSavedFilter;
use App\Filament\Agent\Resources\SavedFilters\Pages\EditSavedFilter;
use App\Filament\Agent\Resources\SavedFilters\Pages\ListSavedFilters;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
use App\Models\SavedFilter;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class SavedFilterResource extends Resource
{
    protected static ?string $model = SavedFilter::class;

    protected static ?string $modelLabel = 'فیلتر ذخیره‌شده';

    protected static ?string $pluralModelLabel = 'فیلترهای ذخیره‌شده';

    protected static ?string $navigationLabel = 'فیلترهای من';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::savedFilter())->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('module')->label(PersianLabels::field('module'))->formatStateUsing(PersianLabels::value(...))->badge()->sortable(), TextColumn::make('name')->label(PersianLabels::field('name'))->searchable()->sortable(),
            TextColumn::make('sort')->label(PersianLabels::field('sort'))->placeholder('-'), IconColumn::make('is_default')->label(PersianLabels::field('is_default'))->boolean(),
            TextColumn::make('updated_at')->label(PersianLabels::field('updated_at'))->dateTime()->sortable(),
        ])->recordActions([
            EditAction::make(),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (SavedFilter $record): bool => Gate::allows('delete', $record))
                ->action(function (SavedFilter $record): void {
                    Gate::authorize('delete', $record);
                    app(SavedFilterService::class)->delete(self::actor(), $record);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavedFilters::route('/'), 'create' => CreateSavedFilter::route('/create'),
            'edit' => EditSavedFilter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', self::actor()->getKey());
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
