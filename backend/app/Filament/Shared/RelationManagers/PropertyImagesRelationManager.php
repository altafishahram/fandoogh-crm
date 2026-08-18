<?php

declare(strict_types=1);

namespace App\Filament\Shared\RelationManagers;

use App\Application\Property\Services\PropertyImageService;
use App\Filament\Shared\Support\PersianLabels;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class PropertyImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'تصاویر';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Property && Gate::allows('viewImages', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('original_name')->label(PersianLabels::field('original_name')), TextColumn::make('mime_type')->label(PersianLabels::field('mime_type')),
            TextColumn::make('size_bytes')->label(PersianLabels::field('size_bytes'))->numeric(), TextColumn::make('width')->label(PersianLabels::field('width')), TextColumn::make('height')->label(PersianLabels::field('height')),
            IconColumn::make('is_cover')->label(PersianLabels::field('is_cover'))->boolean(), TextColumn::make('sort_order')->label(PersianLabels::field('sort_order'))->sortable(),
        ])->recordActions([
            Action::make('set_cover')->label('انتخاب به‌عنوان تصویر اصلی')->visible(fn (PropertyImage $record): bool => ! $record->is_cover
                && Gate::allows('updateImages', $this->property()))
                ->action(function (PropertyImage $record): void {
                    Gate::authorize('updateImages', $this->property());
                    app(PropertyImageService::class)->update(
                        $this->property(), $record, $this->actor(), $record->sort_order, true,
                        CarbonImmutable::parse((string) $record->updated_at),
                    );
                }),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('deleteImages', $this->property()))
                ->action(function (PropertyImage $record): void {
                    Gate::authorize('deleteImages', $this->property());
                    app(PropertyImageService::class)->delete($this->property(), $record, $this->actor());
                }),
        ])->defaultSort('sort_order');
    }

    private function property(): Property
    {
        $record = $this->getOwnerRecord();
        abort_unless($record instanceof Property, 404);

        return $record;
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
