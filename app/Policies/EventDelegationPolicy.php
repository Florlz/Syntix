<?php

namespace App\Policies;

use App\Models\EventDelegation;
use App\Models\User;

class EventDelegationPolicy
{
    public function view(User $actor, EventDelegation $delegation): bool
    {
        return $actor->isActive()
            && $actor->hasAdminAccess($delegation->event_id);
    }

    public function create(User $actor, EventDelegation $delegation): bool
    {
        return $this->view($actor, $delegation);
    }
}
