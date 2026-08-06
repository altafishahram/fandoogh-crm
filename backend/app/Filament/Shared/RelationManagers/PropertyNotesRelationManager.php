<?php

declare(strict_types=1);

namespace App\Filament\Shared\RelationManagers;

use App\Application\Property\Services\PropertyNoteService;
use App\Filament\Shared\Support\PersianLabels;
use App\Models\Property;
use App\Models\PropertyNote;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class PropertyNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'یادداشت‌ها';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('body')->label(PersianLabels::field('body'))->wrap()->limit(160), TextColumn::make('author.name')->label(PersianLabels::field('author.name')),
            TextColumn::make('created_at')->label(PersianLabels::field('created_at'))->dateTime()->sortable(),
        ])->recordActions([
            Action::make('edit')->label('ویرایش')->schema([Textarea::make('body')->label(PersianLabels::field('body'))->required()->maxLength(5000)])
                ->fillForm(fn (PropertyNote $record): array => ['body' => $record->body])
                ->visible(fn (): bool => Gate::allows('manageNotes', $this->property()))
                ->action(function (PropertyNote $record, array $data): void {
                    Gate::authorize('manageNotes', $this->property());
                    app(PropertyNoteService::class)->update($record, (string) $data['body']);
                }),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('manageNotes', $this->property()))
                ->action(function (PropertyNote $record): void {
                    Gate::authorize('manageNotes', $this->property());
                    app(PropertyNoteService::class)->delete($record);
                }),
        ])->defaultSort('created_at', 'desc');
    }

    private function property(): Property
    {
        $record = $this->getOwnerRecord();
        abort_unless($record instanceof Property, 404);

        return $record;
    }
}
