<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Users;

use App\Application\User\Services\ActivateUserService;
use App\Application\User\Services\DeactivateUserService;
use App\Application\User\Services\ResetUserPasswordService;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\Users\Pages\CreateUser;
use App\Filament\Agency\Resources\Users\Pages\EditUser;
use App\Filament\Agency\Resources\Users\Pages\ListUsers;
use App\Filament\Agency\Resources\Users\Pages\ViewUser;
use App\Filament\Shared\Support\PersianDate;
use App\Filament\Shared\Support\PersianLabels;
use App\Filament\Shared\Support\ResourceForms;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'کارشناس';

    protected static ?string $pluralModelLabel = 'کارشناسان';

    protected static ?string $navigationLabel = 'کارشناسان';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ResourceForms::user(false))->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(PersianLabels::field('name')), TextEntry::make('email')->label(PersianLabels::field('email')), TextEntry::make('phone')->label(PersianLabels::field('phone'))->placeholder('-'),
            IconEntry::make('is_active')->label(PersianLabels::field('is_active'))->boolean(), IconEntry::make('must_change_password')->label(PersianLabels::field('must_change_password'))->boolean(),
            TextEntry::make('last_login_at')->label(PersianLabels::field('last_login_at'))->formatStateUsing(PersianDate::format(...))->placeholder('—'),
            TextEntry::make('assigned_properties_count')->label(PersianLabels::field('assigned_properties_count'))->state(fn (User $record): int => $record->assignedProperties()->count()),
            TextEntry::make('assigned_customers_count')->label(PersianLabels::field('assigned_customers_count'))->state(fn (User $record): int => $record->assignedCustomers()->count()),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(PersianLabels::field('name'))->searchable()->sortable(), TextColumn::make('email')->label(PersianLabels::field('email'))->searchable(),
            TextColumn::make('phone')->label(PersianLabels::field('phone'))->searchable(), IconColumn::make('is_active')->label(PersianLabels::field('is_active'))->boolean(),
            TextColumn::make('assigned_properties_count')->counts('assignedProperties')->label(PersianLabels::field('assigned_properties_count'))->sortable(),
            TextColumn::make('assigned_customers_count')->counts('assignedCustomers')->label(PersianLabels::field('assigned_customers_count'))->sortable(),
            TextColumn::make('last_login_at')->label(PersianLabels::field('last_login_at'))->formatStateUsing(PersianDate::format(...))->sortable(),
        ])->filters([TernaryFilter::make('is_active')->label('وضعیت فعالیت')])
            ->recordActions([ViewAction::make(), EditAction::make(), ...self::accountActions()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'), 'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'), 'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = self::actor();

        return parent::getEloquentQuery()->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::Agent->value))
            ->where('agency_id', $actor->agency_id)->whereNull('deleted_at');
    }

    /** @return list<Action> */
    private static function accountActions(): array
    {
        return [
            Action::make('deactivate')->label('غیرفعال‌کردن')->color('danger')->requiresConfirmation()
                ->visible(fn (User $record): bool => $record->is_active && Gate::allows('deactivate', $record))
                ->action(function (User $record): void {
                    Gate::authorize('deactivate', $record);
                    app(DeactivateUserService::class)->execute(self::actor(), $record);
                }),
            Action::make('activate')->label('فعال‌کردن')->color('success')->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->is_active && Gate::allows('deactivate', $record))
                ->action(function (User $record): void {
                    Gate::authorize('deactivate', $record);
                    app(ActivateUserService::class)->execute(self::actor(), $record);
                }),
            Action::make('reset_password')->label('بازنشانی رمز عبور')->schema([
                TextInput::make('temporary_password')->label(PersianLabels::field('temporary_password'))->password()->revealable()->required()->minLength(12),
            ])->visible(fn (User $record): bool => Gate::allows('resetPassword', $record))
                ->action(function (User $record, array $data): void {
                    Gate::authorize('resetPassword', $record);
                    app(ResetUserPasswordService::class)->execute(self::actor(), $record, (string) $data['temporary_password']);
                }),
        ];
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
