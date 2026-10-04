<?php

declare(strict_types=1);

namespace App\Filament\Shared\RelationManagers;

use App\Application\Customer\Services\CustomerNoteService;
use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use App\Models\Customer;
use App\Models\CustomerNote;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CustomerNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'یادداشت‌ها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Customer && Gate::allows('viewNotes', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('body')->label(PersianLabels::field('body'))->wrap()->limit(160), TextColumn::make('author.name')->label(PersianLabels::field('author.name')),
            TextColumn::make('created_at')->label(PersianLabels::field('created_at'))->formatStateUsing(PersianDate::format(...))->sortable(),
        ])->recordActions([
            Action::make('edit')->label('ویرایش')->schema([Textarea::make('body')->label(PersianLabels::field('body'))->required()->maxLength(5000)])
                ->fillForm(fn (CustomerNote $record): array => ['body' => $record->body])
                ->visible(fn (): bool => Gate::allows('updateNotes', $this->customer()))
                ->action(function (CustomerNote $record, array $data): void {
                    Gate::authorize('updateNotes', $this->customer());
                    app(CustomerNoteService::class)->update($record, (string) $data['body']);
                }),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('deleteNotes', $this->customer()))
                ->action(function (CustomerNote $record): void {
                    Gate::authorize('deleteNotes', $this->customer());
                    app(CustomerNoteService::class)->delete($record);
                }),
        ])->defaultSort('created_at', 'desc');
    }

    private function customer(): Customer
    {
        $record = $this->getOwnerRecord();
        abort_unless($record instanceof Customer, 404);

        return $record;
    }
}
