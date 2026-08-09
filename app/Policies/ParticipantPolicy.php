<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

class ParticipantPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isGlobalAdmin();
    }

    public function view(User $actor, Participant $participant): bool
    {
        return $actor->hasAdminAccess($participant->event_id);
    }

    public function create(User $actor): bool
    {
        return $actor->isGlobalAdmin();
    }

    public function update(User $actor, Participant $participant): bool
    {
        return $this->view($actor, $participant);
    }

    public function delete(User $actor, Participant $participant): bool
    {
        return false;
    }
}
