<?php

declare(strict_types=1);

namespace App\Filament\Shared\RelationManagers;

use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CustomerHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'تاریخچه تغییرات';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action')->label('نوع تغییر')->formatStateUsing(PersianLabels::value(...))->badge(),
            TextColumn::make('from_status')->label('وضعیت قبلی')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
            TextColumn::make('to_status')->label('وضعیت جدید')->formatStateUsing(PersianLabels::value(...))->placeholder('—'),
            TextColumn::make('changed_fields')->label('فیلدهای تغییرکرده')
                ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode('، ', $state) : '—')->wrap(),
            TextColumn::make('reason')->label('دلیل')->wrap()->placeholder('—'),
            TextColumn::make('actor.name')->label('انجام‌دهنده')->placeholder('سامانه'),
            TextColumn::make('occurred_at')->label('تاریخ')->formatStateUsing(PersianDate::format(...))->sortable(),
        ])->defaultSort('occurred_at', 'desc');
    }
}
