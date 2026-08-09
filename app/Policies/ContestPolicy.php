<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\Contest;
use App\Models\User;

class ContestPolicy
{
    public function view(User $actor, Contest $contest): bool
    {
        $eventId = $contest->eventId();

        if ($eventId === null || ! $actor->isActive()) {
            return false;
        }

        return $actor->hasActiveEventRole($eventId, EventRole::Admin)
            || $actor->canScoreContest($contest);
    }

    public function score(User $actor, Contest $contest): bool
    {
        return $actor->canScoreContest($contest);
    }

    public function update(User $actor, Contest $contest): bool
    {
        return $this->score($actor, $contest);
    }
}
