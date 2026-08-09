<?php

namespace App\Policies;

use App\Enums\EventRole;
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

        return $role !== EventRole::Admin && $actor->hasAdminAccess($event);
    }

    public function revoke(User $actor, EventUserRole $membership): bool
    {
        return $membership->isActive()
            && $actor->hasAdminAccess($membership->event_id);
    }
}
