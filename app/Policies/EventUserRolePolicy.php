<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Enums\PlatformCapability;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\User;

class EventUserRolePolicy
{
    public function grant(
        User $actor,
        Event $event,
        User $target,
        EventRole|string $role,
    ): bool {
        $role = $role instanceof EventRole ? $role : EventRole::from($role);

        if (! $actor->isActive() || ! $target->isActive() || $event->isArchived()) {
            return false;
        }

        $isFirstAdmin = $role === EventRole::Admin
            && ! $event->hasActiveAdmin()
            && $actor->hasActivePlatformCapability(PlatformCapability::EventCreator);

        return $isFirstAdmin || $actor->hasActiveEventRole($event, EventRole::Admin);
    }

    public function revoke(User $actor, EventUserRole $membership): bool
    {
        return $membership->isActive()
            && $actor->hasActiveEventRole($membership->event_id, EventRole::Admin);
    }
}
