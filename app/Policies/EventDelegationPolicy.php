<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\EventDelegation;
use App\Models\User;

class EventDelegationPolicy
{
    public function view(User $actor, EventDelegation $delegation): bool
    {
        return $actor->isActive()
            && $actor->hasActiveEventRole($delegation->event_id, EventRole::Admin);
    }

    public function create(User $actor, EventDelegation $delegation): bool
    {
        return $this->view($actor, $delegation);
    }
}
