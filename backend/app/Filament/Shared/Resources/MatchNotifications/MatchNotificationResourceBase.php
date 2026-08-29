<?php

declare(strict_types=1);

namespace App\Filament\Shared\Resources\MatchNotifications;

use App\Application\Matching\Services\MarkMatchNotificationReadService;
use App\Filament\Shared\Support\PersianDate;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

abstract class MatchNotificationResourceBase extends Resource
{
    protected static ?string $model = MatchNotification::class;

    protected static ?string $modelLabel = 'اعلان تطبیق';

    protected static ?string $pluralModelLabel = 'اعلان‌های تطبیق';

    protected static ?string $navigationLabel = 'اعلان‌های تطبیق';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $recordTitleAttribute = 'title';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اعلان تطبیق')->schema([
                TextEntry::make('title')->label('عنوان')->weight(FontWeight::Bold),
                TextEntry::make('body')->label('توضیحات')->placeholder('—')->columnSpanFull(),
                TextEntry::make('version')->label('نسخه اعلان'),
                TextEntry::make('created_at')->label('تاریخ ایجاد')->formatStateUsing(PersianDate::format(...)),
                TextEntry::make('updated_at')->label('آخرین تغییر')->formatStateUsing(PersianDate::format(...)),
            ])->columns(4),
            Section::make('جزئیات تطبیق')->schema([
                TextEntry::make('match.score')->label('امتیاز کل')->suffix(' از ۱۰۰')->placeholder('—'),
                TextEntry::make('match.financial_score')->label('امتیاز مالی')->placeholder('—'),
                TextEntry::make('match.area_score')->label('امتیاز متراژ')->placeholder('—'),
                TextEntry::make('match.feature_score')->label('امتیاز امکانات')->placeholder('—'),
                TextEntry::make('match.match_mode')->label('شیوه تطبیق')->formatStateUsing(self::matchModeLabel(...)),
                TextEntry::make('match.property_rank')->label('رتبه ملک')->placeholder('—'),
                TextEntry::make('match.customer_rank')->label('رتبه مشتری')->placeholder('—'),
                TextEntry::make('match.property.title')->label('ملک')->url(
                    fn (MatchNotification $record): ?string => static::propertyUrl($record),
                ),
                TextEntry::make('match.customer.full_name')->label('مشتری')->url(
                    fn (MatchNotification $record): ?string => static::customerUrl($record),
                ),
            ])->columns(3),
            Section::make('بازه مالی قابل اجرا')->schema([
                TextEntry::make('match.matched_deposit_min')->label('حداقل ودیعه')->numeric()->suffix(' تومان')->placeholder('—'),
                TextEntry::make('match.matched_deposit_max')->label('حداکثر ودیعه')->numeric()->suffix(' تومان')->placeholder('—'),
                TextEntry::make('match.matched_rent_min')->label('حداقل اجاره')->numeric()->suffix(' تومان')->placeholder('—'),
                TextEntry::make('match.matched_rent_max')->label('حداکثر اجاره')->numeric()->suffix(' تومان')->placeholder('—'),
            ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('اعلان')->searchable()->weight(FontWeight::Bold)->wrap(),
                TextColumn::make('match.property.title')->label('ملک')->searchable()->wrap(),
                TextColumn::make('match.customer.full_name')->label('مشتری')->searchable()->wrap(),
                TextColumn::make('match.score')->label('امتیاز')->numeric()->suffix(' از ۱۰۰')->sortable(),
                TextColumn::make('match.match_mode')->label('شیوه تطبیق')->formatStateUsing(self::matchModeLabel(...))->badge(),
                TextColumn::make('read_state')->label('وضعیت')->state(
                    fn (MatchNotification $record): string => static::isRead($record) ? 'خوانده‌شده' : 'خوانده‌نشده',
                )->badge()->color(
                    fn (MatchNotification $record): string => static::isRead($record) ? 'gray' : 'warning',
                ),
                TextColumn::make('created_at')->label('تاریخ ایجاد')->formatStateUsing(PersianDate::format(...))->sortable(),
            ])
            ->filters([
                Filter::make('unread')->label('فقط خوانده‌نشده')->query(
                    fn (Builder $query): Builder => self::applyUnreadFilter($query, static::actor()),
                ),
            ])
            ->recordActions([
                ViewAction::make(),
                static::markAsReadAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = static::actor();
        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        return parent::getEloquentQuery()
            ->where($notificationsTable.'.agency_id', $actor->agency_id)
            ->with(['match.property', 'match.customer'])
            ->addSelect([
                'read_version' => DB::table($readsTable)
                    ->select('notification_version')
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $actor->agency_id)
                    ->where($readsTable.'.user_id', $actor->getKey())
                    ->limit(1),
                'read_at' => DB::table($readsTable)
                    ->select('read_at')
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $actor->agency_id)
                    ->where($readsTable.'.user_id', $actor->getKey())
                    ->limit(1),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $actor = auth()->user();
            if (! $actor instanceof User || ! Gate::allows('viewAny', MatchNotification::class)) {
                return null;
            }

            $count = self::unreadCount($actor);

            return $count > 0 ? (string) $count : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function markAsReadAction(): Action
    {
        return Action::make('mark_as_read')
            ->label('علامت‌گذاری به‌عنوان خوانده‌شده')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (MatchNotification $record): bool => ! static::isRead($record))
            ->action(function (MatchNotification $record): void {
                Gate::authorize('markAsRead', $record);
                $read = app(MarkMatchNotificationReadService::class)->execute(static::actor(), $record);
                $record->setAttribute('read_version', $read->getAttribute('notification_version'));
                $record->setAttribute('read_at', $read->getAttribute('read_at'));

                FilamentNotification::make()
                    ->title('اعلان به‌عنوان خوانده‌شده ثبت شد.')
                    ->success()
                    ->send();
            });
    }

    public static function isRead(MatchNotification $notification): bool
    {
        $version = (int) ($notification->getAttribute('version') ?? 1);
        $readVersion = $notification->getAttribute('read_version');

        return $readVersion !== null && (int) $readVersion >= $version;
    }

    protected static function propertyUrl(MatchNotification $notification): ?string
    {
        $match = $notification->getRelationValue('match');
        $property = is_object($match) && method_exists($match, 'getRelationValue')
            ? $match->getRelationValue('property') : null;

        return is_object($property) && method_exists($property, 'getKey')
            ? static::propertyResourceClass()::getUrl('view', ['record' => $property->getKey()]) : null;
    }

    protected static function customerUrl(MatchNotification $notification): ?string
    {
        $match = $notification->getRelationValue('match');
        $customer = is_object($match) && method_exists($match, 'getRelationValue')
            ? $match->getRelationValue('customer') : null;

        return is_object($customer) && method_exists($customer, 'getKey')
            ? static::customerResourceClass()::getUrl('view', ['record' => $customer->getKey()]) : null;
    }

    /** @return class-string<resource> */
    abstract protected static function propertyResourceClass(): string;

    /** @return class-string<resource> */
    abstract protected static function customerResourceClass(): string;

    private static function unreadCount(User $actor): int
    {
        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        return (int) MatchNotification::query()
            ->where($notificationsTable.'.agency_id', $actor->agency_id)
            ->whereNotExists(function (QueryBuilder $subquery) use ($notificationsTable, $readsTable, $actor): void {
                $subquery->selectRaw('1')
                    ->from($readsTable)
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $actor->agency_id)
                    ->where($readsTable.'.user_id', $actor->getKey())
                    ->whereColumn($readsTable.'.notification_version', '>=', $notificationsTable.'.version');
            })
            ->count();
    }

    /**
     * @param  Builder<MatchNotification>  $query
     * @return Builder<MatchNotification>
     */
    private static function applyUnreadFilter(Builder $query, User $actor): Builder
    {
        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        return $query->whereNotExists(function (QueryBuilder $subquery) use ($notificationsTable, $readsTable, $actor): void {
            $subquery->selectRaw('1')
                ->from($readsTable)
                ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                ->where($readsTable.'.agency_id', $actor->agency_id)
                ->where($readsTable.'.user_id', $actor->getKey())
                ->whereColumn($readsTable.'.notification_version', '>=', $notificationsTable.'.version');
        });
    }

    protected static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private static function matchModeLabel(mixed $mode): string
    {
        $value = $mode instanceof BackedEnum ? $mode->value : $mode;

        return match ($value) {
            'direct' => 'مستقیم',
            'converted', 'conversion' => 'با تبدیل ودیعه و اجاره',
            default => $value === null || $value === '' ? '—' : (string) $value,
        };
    }
}
