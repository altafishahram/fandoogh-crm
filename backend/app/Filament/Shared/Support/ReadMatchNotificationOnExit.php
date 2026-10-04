<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Matching\Services\MarkMatchNotificationReadService;
use App\Models\MatchNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ReadMatchNotificationOnExit
{
    public function __invoke(Request $request, int $record, MarkMatchNotificationReadService $service): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $panel = Filament::getCurrentPanel();
        abort_unless($panel !== null && in_array($panel->getId(), ['agency', 'agent'], true) && $actor->canAccessPanel($panel), 403);
        Gate::authorize('viewAny', MatchNotification::class);
        $notification = MatchNotification::query()->where('agency_id', $actor->agency_id)->findOrFail($record);
        Gate::authorize('markAsRead', $notification);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1', 'max:'.(int) $notification->version]]);

        // Only acknowledge the version displayed, never a newer unseen update.
        $notification->setAttribute('version', (int) $data['version']);
        $service->execute($actor, $notification);

        return response()->noContent();
    }
}
