<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Shared authorization boundary for event operations that can change history. */
final class EventOperationGuard
{
    public static function assertAdmin(User $actor, ?Event $event, string $message = 'Only the active Global Admin can manage this Event.'): void
    {
        if ($event === null || ! $actor->hasAdminAccess($event)) {
            throw new AuthorizationException($message);
        }
    }

    public static function assertMutable(User $actor, ?Event $event, string $message = 'Only the active Global Admin can manage this Event.'): void
    {
        self::assertAdmin($actor, $event, $message);

        if ($event->isArchived()) {
            throw new AuthorizationException('Archived events are read-only.');
        }
    }
}
