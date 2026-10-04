<?php

declare(strict_types=1);

namespace App\Application\Matching\Services;

use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** One selection rule for related lists and their per-user card summaries. */
final class RelatedMatchQuery
{
    /** @return Builder<MatchNotification> */
    public function forProperty(Property $property, User $user): Builder
    {
        return $this->forRecord($property, $user, 'property');
    }

    /** @return Builder<MatchNotification> */
    public function forCustomer(Customer $customer, User $user): Builder
    {
        return $this->forRecord($customer, $user, 'customer');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function withSummaries(Builder $query, User $user, string $side): Builder
    {
        $this->assertSide($side);
        $table = $query->getModel()->getTable();
        if ($query->getQuery()->columns === null) {
            $query->select($table.'.*');
        }
        $selected = $this->selected($user, $side)
            ->whereColumn('m.'.$side.'_id', $table.'.id')
            ->whereColumn('m.agency_id', $table.'.agency_id');

        return $query->addSelect([
            'match_count' => (clone $selected)->selectRaw('COUNT(*)'),
            'unread_match_count' => $this->unread(clone $selected, $user)->selectRaw('COUNT(*)'),
        ]);
    }

    /**
     * @param  Builder<MatchNotification>  $query
     * @return Builder<MatchNotification>
     */
    public function withReadState(Builder $query, User $user): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('match_notifications.*');
        }
        $reads = DB::table('match_notification_reads as current_read')
            ->whereColumn('current_read.match_notification_id', 'match_notifications.id')
            ->whereColumn('current_read.agency_id', 'match_notifications.agency_id')
            ->where('current_read.user_id', $user->getKey());

        return $query->addSelect([
            'read_version' => (clone $reads)->select('current_read.notification_version')->limit(1),
            'read_at' => (clone $reads)->select('current_read.read_at')->limit(1),
        ]);
    }

    /**
     * @template TModel of Property|Customer
     *
     * @param  TModel  $record
     * @return TModel
     */
    public function loadSummary(Property|Customer $record, User $user): Property|Customer
    {
        $side = $record instanceof Property ? 'property' : 'customer';
        $query = $record instanceof Property
            ? $this->withSummaries(Property::withTrashed(), $user, $side)
            : $this->withSummaries(Customer::withTrashed(), $user, $side);
        $fresh = $query->where('agency_id', $user->agency_id)->whereKey($record->getKey())->first();
        $record->setAttribute('match_count', $fresh?->getAttribute('match_count'));
        $record->setAttribute('unread_match_count', $fresh?->getAttribute('unread_match_count'));

        return $record;
    }

    /** @return array{count:int, unread_count:int}|null */
    public static function summaryOf(Model $record): ?array
    {
        $attributes = $record->getAttributes();
        if (! isset($attributes['match_count'], $attributes['unread_match_count'])) {
            return null;
        }

        return [
            'count' => (int) $attributes['match_count'],
            'unread_count' => (int) $attributes['unread_match_count'],
        ];
    }

    public static function shortReason(?Model $match): string
    {
        if ($match === null) {
            return '';
        }
        $mode = $match->getAttribute('match_mode');
        $mode = $mode instanceof \BackedEnum ? $mode->value : $mode;

        return 'نوع ملک و متراژ مطابق نیاز مشتری است؛ '.($mode === 'converted'
            ? 'با تبدیل ودیعه و اجاره، شرایط مالی در بازهٔ مشتری قرار می‌گیرد.'
            : 'شرایط مالی در بازهٔ موردنظر مشتری قرار دارد.');
    }

    /** @param Builder<MatchNotification> $query */
    public function unreadCount(Builder $query, User $user): int
    {
        return $query->whereNotExists(fn (QueryBuilder $reads) => $reads
            ->selectRaw('1')->from('match_notification_reads as related_read')
            ->whereColumn('related_read.match_notification_id', 'match_notifications.id')
            ->whereColumn('related_read.agency_id', 'match_notifications.agency_id')
            ->where('related_read.user_id', $user->getKey())
            ->whereColumn('related_read.notification_version', '>=', 'match_notifications.version'))
            ->count();
    }

    /** @return Builder<MatchNotification> */
    private function forRecord(Property|Customer $record, User $user, string $side): Builder
    {
        abort_unless($user->agency_id !== null && (int) $record->agency_id === (int) $user->agency_id, 404);
        $selected = $this->selected($user, $side)
            ->where('m.'.$side.'_id', $record->getKey())
            ->select(['n.id', 'm.'.$side.'_rank as side_rank']);

        return $this->withReadState(MatchNotification::query(), $user)
            ->where('match_notifications.agency_id', $user->agency_id)
            ->joinSub($selected, 'related_selection', 'related_selection.id', '=', 'match_notifications.id')
            ->orderBy('related_selection.side_rank');
    }

    /** Match ranks outside this side's top twenty are null, even when stored for the other side. */
    private function selected(User $user, string $side): QueryBuilder
    {
        $this->assertSide($side);

        return DB::table('property_customer_matches as m')
            ->join('match_notifications as n', function ($join): void {
                $join->on('n.property_customer_match_id', '=', 'm.id')->on('n.agency_id', '=', 'm.agency_id');
            })
            ->join('properties as p', function ($join): void {
                $join->on('p.id', '=', 'm.property_id')->on('p.agency_id', '=', 'm.agency_id');
            })
            ->join('customers as c', function ($join): void {
                $join->on('c.id', '=', 'm.customer_id')->on('c.agency_id', '=', 'm.agency_id');
            })
            ->where('m.agency_id', $user->agency_id)
            ->whereBetween('m.'.$side.'_rank', [1, 20])
            ->where('p.status', 'available')->whereNull('p.deleted_at')->whereNotNull('p.matching_eligible_at')
            ->where('c.status', 'active')->whereNull('c.deleted_at')->whereNotNull('c.matching_eligible_at');
    }

    private function unread(QueryBuilder $query, User $user): QueryBuilder
    {
        return $query->whereNotExists(fn (QueryBuilder $reads) => $reads
            ->selectRaw('1')->from('match_notification_reads as summary_read')
            ->whereColumn('summary_read.match_notification_id', 'n.id')
            ->whereColumn('summary_read.agency_id', 'n.agency_id')
            ->where('summary_read.user_id', $user->getKey())
            ->whereColumn('summary_read.notification_version', '>=', 'n.version'));
    }

    private function assertSide(string $side): void
    {
        if (! in_array($side, ['property', 'customer'], true)) {
            throw new \InvalidArgumentException('Unknown matching side.');
        }
    }
}
