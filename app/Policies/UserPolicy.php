<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $actor->isActive() && $actor->getKey() === $target->getKey();
    }

    public function update(User $actor, User $target): bool
    {
        return $this->view($actor, $target);
    }

    public function disable(User $actor, User $target, ?Event $event = null): bool
    {
        return $this->isEventAdmin($actor, $event);
    }

    public function enable(User $actor, User $target, ?Event $event = null): bool
    {
        return $this->isEventAdmin($actor, $event);
    }

    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    private function isEventAdmin(User $actor, ?Event $event): bool
    {
        if (! $actor->isActive()) {
            return false;
        }

        if ($event !== null) {
            return $actor->hasAdminAccess($event);
        }

        return $actor->hasAnyAdminAccess();
    }
}
