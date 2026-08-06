<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Customers;

use App\Application\Customer\Services\CustomerNoteService;
use App\Application\Customer\Services\DeleteCustomerService;
use App\Application\Customer\Services\RestoreCustomerService;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Agency\Resources\Customers\Pages\EditCustomer;
use App\Filament\Agency\Resources\Customers\Pages\ListCustomers;
use App\Filament\Agency\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Shared\RelationManagers\CustomerNotesRelationManager;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
use App\Models\Customer;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Illuminate\Support\Facades\Gate;

final class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'مشتری';

    protected static ?string $pluralModelLabel = 'مشتریان';

    protected static ?string $navigationLabel = 'مشتریان';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::customer(false))->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('first_name')->label(PersianLabels::field('first_name')), TextEntry::make('last_name')->label(PersianLabels::field('last_name')), TextEntry::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge(),
            TextEntry::make('intent')->label(PersianLabels::field('intent'))->formatStateUsing(PersianLabels::value(...))->badge(), TextEntry::make('assignedAgent.name')->label(PersianLabels::field('assignedAgent.name')),
            TextEntry::make('mobile')->label(PersianLabels::field('mobile')), TextEntry::make('phone')->label(PersianLabels::field('phone'))->placeholder('-'), TextEntry::make('email')->label(PersianLabels::field('email'))->placeholder('-'),
            TextEntry::make('preferred_contact_method')->label(PersianLabels::field('preferred_contact_method'))->formatStateUsing(PersianLabels::value(...))->badge(), TextEntry::make('budget_min')->label(PersianLabels::field('budget_min'))->numeric()->placeholder('-'),
            TextEntry::make('budget_max')->label(PersianLabels::field('budget_max'))->numeric()->placeholder('-'), TextEntry::make('desired_city')->label(PersianLabels::field('desired_city'))->placeholder('-'),
            TextEntry::make('desired_district')->label(PersianLabels::field('desired_district'))->placeholder('-'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('first_name')->label(PersianLabels::field('first_name'))->searchable()->sortable(), TextColumn::make('last_name')->label(PersianLabels::field('last_name'))->searchable()->sortable(),
            TextColumn::make('mobile')->label(PersianLabels::field('mobile'))->searchable(), TextColumn::make('status')->label(PersianLabels::field('status'))->formatStateUsing(PersianLabels::value(...))->badge()->sortable(),
            TextColumn::make('intent')->label(PersianLabels::field('intent'))->formatStateUsing(PersianLabels::value(...))->badge(), TextColumn::make('assignedAgent.name')->label(PersianLabels::field('assignedAgent.name'))->searchable(),
            TextColumn::make('desired_city')->label(PersianLabels::field('desired_city'))->searchable(), TextColumn::make('updated_at')->label(PersianLabels::field('updated_at'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->label(PersianLabels::field('status'))->options([
                'active' => 'فعال', 'inactive' => 'غیرفعال', 'converted' => 'نهایی‌شده', 'lost' => 'از دست‌رفته',
            ]),
            SelectFilter::make('intent')->label(PersianLabels::field('intent'))->options(['buy' => 'خرید', 'rent' => 'اجاره']),
            SelectFilter::make('assigned_agent_id')->label(PersianLabels::field('assigned_agent_id'))->options(fn (): array => User::query()
                ->where('agency_id', self::actor()->agency_id)->where('is_active', true)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::Agent->value))
                ->orderBy('name')->pluck('name', 'id')->all()),
            TrashedFilter::make(),
        ])->recordActions([
            ViewAction::make(), EditAction::make(),
            Action::make('add_note')->label('افزودن یادداشت')->schema([Textarea::make('body')->label(PersianLabels::field('body'))->required()->maxLength(5000)])
                ->visible(fn (Customer $record): bool => ! $record->trashed() && Gate::allows('manageNotes', $record))
                ->action(function (Customer $record, array $data): void {
                    Gate::authorize('manageNotes', $record);
                    app(CustomerNoteService::class)->create($record, self::actor(), (string) $data['body']);
                }),
            Action::make('delete')->label('حذف')->color('danger')->requiresConfirmation()
                ->visible(fn (Customer $record): bool => ! $record->trashed() && Gate::allows('delete', $record))
                ->action(function (Customer $record): void {
                    Gate::authorize('delete', $record);
                    app(DeleteCustomerService::class)->execute($record);
                }),
            Action::make('restore')->label('بازیابی')->color('success')->requiresConfirmation()
                ->visible(fn (Customer $record): bool => $record->trashed() && Gate::allows('restore', $record))
                ->action(function (Customer $record): void {
                    Gate::authorize('restore', $record);
                    app(RestoreCustomerService::class)->execute($record);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'), 'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'), 'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [CustomerNotesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
