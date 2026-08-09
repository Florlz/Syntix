<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $actor): bool
    {
        return $actor->isGlobalAdmin();
    }

    public function view(User $actor, Event $event): bool
    {
        return $actor->isGlobalAdmin()
            || ($event->userRoles()
                ->where('user_id', $actor->getKey())
                ->whereNull('revoked_at')
                ->exists()
                && $actor->isActive());
    }

    public function update(User $actor, Event $event): bool
    {
        return $actor->hasAdminAccess($event)
            && ! $event->isArchived();
    }
}
