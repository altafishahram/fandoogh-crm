<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Matching\Services\MarkMatchNotificationReadService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Http\Resources\Api\V1\MatchNotification\MatchNotificationResource;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\PropertyCustomerMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class MatchNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('viewAny', MatchNotification::class);

        $validated = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = $this->notificationQuery($user);

        if ((bool) ($validated['unread_only'] ?? false)) {
            $this->applyUnreadFilter($query, $user);
        }

        $table = (new MatchNotification)->getTable();
        $paginator = $query
            ->orderByDesc($table.'.created_at')
            ->orderByDesc($table.'.id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        $this->attachMatches($paginator->getCollection(), $user);

        return response()->json([
            'data' => MatchNotificationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $this->unreadCountFor($user),
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('viewAny', MatchNotification::class);

        return response()->json([
            'data' => ['count' => $this->unreadCountFor($user)],
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    public function show(Request $request, int $matchNotification): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('viewAny', MatchNotification::class);

        /** @var MatchNotification $notification */
        $notification = $this->notificationQuery($user)->findOrFail($matchNotification);
        Gate::authorize('view', $notification);
        $this->attachMatches(new Collection([$notification]), $user);

        return $this->singleResponse($notification, $request);
    }

    public function markRead(
        Request $request,
        int $matchNotification,
        MarkMatchNotificationReadService $service,
    ): JsonResponse {
        $user = $this->user($request);
        Gate::authorize('viewAny', MatchNotification::class);

        /** @var MatchNotification $notification */
        $notification = $this->notificationQuery($user)->findOrFail($matchNotification);
        Gate::authorize('markAsRead', $notification);
        $version = (int) $notification->version;
        $validated = $request->validate([
            'notification_version' => ['nullable', 'integer', 'min:1', 'max:'.$version],
        ]);
        // An offline or just-closed detail may refer to an earlier version.
        $notification->setAttribute('version', (int) ($validated['notification_version'] ?? $version));
        $read = $service->execute($user, $notification);
        $notification->setAttribute('version', $version);

        $notification->setAttribute('read_version', $read->getAttribute('notification_version'));
        $notification->setAttribute('read_at', $read->getAttribute('read_at'));
        $this->attachMatches(new Collection([$notification]), $user);

        return $this->singleResponse($notification, $request);
    }

    /** @return Builder<MatchNotification> */
    private function notificationQuery(User $user): Builder
    {
        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        return MatchNotification::query()
            ->where($notificationsTable.'.agency_id', $user->agency_id)
            ->addSelect([
                'read_version' => MatchNotificationRead::query()
                    ->select('notification_version')
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $user->agency_id)
                    ->where($readsTable.'.user_id', $user->getKey())
                    ->limit(1),
                'read_at' => MatchNotificationRead::query()
                    ->select('read_at')
                    ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                    ->where($readsTable.'.agency_id', $user->agency_id)
                    ->where($readsTable.'.user_id', $user->getKey())
                    ->limit(1),
            ]);
    }

    /** @param Builder<MatchNotification> $query */
    private function applyUnreadFilter(Builder $query, User $user): void
    {
        $notificationsTable = (new MatchNotification)->getTable();
        $readsTable = (new MatchNotificationRead)->getTable();

        $query->whereNotExists(function (QueryBuilder $subquery) use ($notificationsTable, $readsTable, $user): void {
            $subquery->selectRaw('1')
                ->from($readsTable)
                ->whereColumn($readsTable.'.match_notification_id', $notificationsTable.'.id')
                ->where($readsTable.'.agency_id', $user->agency_id)
                ->where($readsTable.'.user_id', $user->getKey())
                ->whereColumn($readsTable.'.notification_version', '>=', $notificationsTable.'.version');
        });
    }

    private function unreadCountFor(User $user): int
    {
        $query = $this->notificationQuery($user);
        $this->applyUnreadFilter($query, $user);

        return (int) $query->count();
    }

    /** @param Collection<int, MatchNotification> $notifications */
    private function attachMatches(Collection $notifications, User $user): void
    {
        $matchIds = $notifications
            ->pluck('property_customer_match_id')
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($matchIds->isEmpty()) {
            $notifications->each(static fn (MatchNotification $notification): MatchNotification => $notification->setRelation('match', null));

            return;
        }

        $matches = PropertyCustomerMatch::query()
            ->where('agency_id', $user->agency_id)
            ->whereIn('id', $matchIds)
            ->with([
                'property.images' => static fn ($images) => $images->orderBy('sort_order'),
                'customer',
            ])
            ->get()
            ->keyBy('id');

        $notifications->each(static function (MatchNotification $notification) use ($matches): void {
            $id = (int) $notification->getAttribute('property_customer_match_id');
            $notification->setRelation('match', $matches->get($id));
        });
    }

    private function singleResponse(MatchNotification $notification, Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new MatchNotificationResource($notification))->resolve($request),
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get(AssignRequestId::ATTRIBUTE);
    }
}
