<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Enums\PlatformCapability;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasActivePlatformCapability(PlatformCapability::EventCreator);
    }

    public function view(User $actor, Event $event): bool
    {
        return $event->userRoles()
            ->where('user_id', $actor->getKey())
            ->whereNull('revoked_at')
            ->exists()
            && $actor->isActive();
    }

    public function update(User $actor, Event $event): bool
    {
        return $actor->hasActiveEventRole($event, EventRole::Admin)
            && ! $event->isArchived();
    }

    public function grantFirstAdmin(User $actor, Event $event): bool
    {
        return $actor->hasActivePlatformCapability(PlatformCapability::EventCreator)
            && ! $event->isArchived()
            && ! $event->hasActiveAdmin();
    }
}
