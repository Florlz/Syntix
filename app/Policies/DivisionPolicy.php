<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

class DivisionPolicy
{
    public function view(User $actor, Division $division): bool
    {
        $eventId = $division->eventId();

        return $eventId !== null
            && $actor->isActive()
            && $actor->hasAdminAccess($eventId);
    }

    public function update(User $actor, Division $division): bool
    {
        return $this->view($actor, $division);
    }
}
