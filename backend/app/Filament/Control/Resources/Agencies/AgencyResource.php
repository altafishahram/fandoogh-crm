<?php

declare(strict_types=1);

namespace App\Filament\Control\Resources\Agencies;

use App\Application\Agency\Services\ActivateAgencyService;
use App\Application\Agency\Services\SuspendAgencyService;
use App\Application\Agency\Services\VerifyAgencyService;
use App\Domain\Tenancy\AgencyScope;
use App\Filament\Control\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Control\Resources\Agencies\Pages\EditAgency;
use App\Filament\Control\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Control\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
use App\Models\Agency;
use App\Models\AgencySettings;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static ?string $modelLabel = 'آژانس';

    protected static ?string $pluralModelLabel = 'آژانس‌ها';

    protected static ?string $navigationLabel = 'آژانس‌ها';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::agency())->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(PersianLabels::field('name')), TextEntry::make('slug')->label(PersianLabels::field('slug')), IconEntry::make('is_active')->label(PersianLabels::field('is_active'))->boolean(),
            TextEntry::make('email')->label(PersianLabels::field('email')), TextEntry::make('phone')->label(PersianLabels::field('phone')), TextEntry::make('city')->label(PersianLabels::field('city')),
            TextEntry::make('province')->label(PersianLabels::field('province')), TextEntry::make('timezone')->label(PersianLabels::field('timezone')), TextEntry::make('locale')->label(PersianLabels::field('locale')),
            TextEntry::make('currency_code')->label(PersianLabels::field('currency_code')),
            TextEntry::make('property_code_prefix')->label(PersianLabels::field('property_code_prefix'))->state(
                fn (Agency $record): mixed => AgencySettings::withoutGlobalScope(AgencyScope::class)
                    ->where('agency_id', $record->getKey())->value('property_code_prefix'),
            ),
            TextEntry::make('default_page_size')->label(PersianLabels::field('default_page_size'))->state(
                fn (Agency $record): mixed => AgencySettings::withoutGlobalScope(AgencyScope::class)
                    ->where('agency_id', $record->getKey())->value('default_page_size'),
            ),
            TextEntry::make('users_count')->label(PersianLabels::field('users_count'))->state(fn (Agency $record): int => $record->users()->count()),
            TextEntry::make('properties_count')->label(PersianLabels::field('properties_count'))->state(fn (Agency $record): int => Property::withoutGlobalScope(AgencyScope::class)
                ->where('agency_id', $record->getKey())->count()),
            TextEntry::make('customers_count')->label(PersianLabels::field('customers_count'))->state(fn (Agency $record): int => Customer::withoutGlobalScope(AgencyScope::class)
                ->where('agency_id', $record->getKey())->count()),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(PersianLabels::field('name'))->searchable()->sortable(), TextColumn::make('slug')->label(PersianLabels::field('slug'))->searchable(),
            IconColumn::make('is_verified')->label('تأیید بازار')->boolean(),
            IconColumn::make('is_active')->label(PersianLabels::field('is_active'))->boolean(), TextColumn::make('city')->label(PersianLabels::field('city'))->searchable(),
            TextColumn::make('timezone')->label(PersianLabels::field('timezone')), TextColumn::make('users_count')->counts('users')->label(PersianLabels::field('users_count')),
            TextColumn::make('activated_at')->label(PersianLabels::field('activated_at'))->formatStateUsing(PersianDate::format(...))->placeholder('—')->sortable(),
        ])->filters([TernaryFilter::make('is_active')->label('وضعیت فعالیت')])->recordActions([
            ViewAction::make(), EditAction::make(),
            Action::make('verify')->label(fn (Agency $record): string => $record->is_verified ? 'لغو تأیید بازار' : 'تأیید برای بازار')->color('success')
                ->visible(fn (): bool => self::actor()->can('agencies.verify'))
                ->action(fn (Agency $record) => app(VerifyAgencyService::class)->execute(self::actor(), $record, ! $record->is_verified)),
            Action::make('activate')->label('فعال‌کردن')->color('success')->requiresConfirmation()
                ->visible(fn (Agency $record): bool => ! $record->is_active && Gate::allows('activate', $record))
                ->action(function (Agency $record): void {
                    Gate::authorize('activate', $record);
                    app(ActivateAgencyService::class)->execute(self::actor(), $record);
                }),
            Action::make('suspend')->label('تعلیق آژانس')->color('danger')->requiresConfirmation()
                ->visible(fn (Agency $record): bool => $record->is_active && Gate::allows('activate', $record))
                ->action(function (Agency $record): void {
                    Gate::authorize('activate', $record);
                    app(SuspendAgencyService::class)->execute(self::actor(), $record);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'), 'create' => CreateAgency::route('/create'),
            'view' => ViewAgency::route('/{record}'), 'edit' => EditAgency::route('/{record}/edit'),
        ];
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
