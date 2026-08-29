<?php

declare(strict_types=1);

namespace App\Application\Matching\Services;

use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MarkMatchNotificationReadService
{
    public function execute(User $user, MatchNotification $notification): MatchNotificationRead
    {
        $version = (int) ($notification->getAttribute('version') ?? 1);

        return DB::transaction(function () use ($user, $notification, $version): MatchNotificationRead {
            $read = MatchNotificationRead::query()
                ->where('agency_id', $user->agency_id)
                ->where('match_notification_id', $notification->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($read === null) {
                $read = new MatchNotificationRead;
                $read->forceFill([
                    'agency_id' => $user->agency_id,
                    'match_notification_id' => $notification->getKey(),
                    'user_id' => $user->getKey(),
                    'notification_version' => $version,
                    'read_at' => now(),
                ]);
                $read->save();

                return $read;
            }

            if ((int) $read->getAttribute('notification_version') < $version) {
                $read->forceFill([
                    'notification_version' => $version,
                    'read_at' => now(),
                ]);
                $read->save();
            }

            return $read;
        });
    }
}
