<?php

declare(strict_types=1);

namespace App\Filament\Shared\RelationManagers;

use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PropertyHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'تاریخچه تغییرات';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action')->label(PersianLabels::field('action'))->formatStateUsing(PersianLabels::value(...))->badge(), TextColumn::make('from_status')->label(PersianLabels::field('from_status'))->formatStateUsing(PersianLabels::value(...))->placeholder('-'),
            TextColumn::make('to_status')->label(PersianLabels::field('to_status'))->formatStateUsing(PersianLabels::value(...))->placeholder('-'), TextColumn::make('reason')->label(PersianLabels::field('reason'))->wrap()->placeholder('-'),
            TextColumn::make('actor.name')->label(PersianLabels::field('actor.name')), TextColumn::make('occurred_at')->label(PersianLabels::field('occurred_at'))->formatStateUsing(PersianDate::format(...))->sortable(),
        ])->defaultSort('occurred_at', 'desc');
    }
}
