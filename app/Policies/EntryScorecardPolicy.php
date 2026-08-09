<?php

namespace App\Policies;

use App\Models\EntryScorecard;
use App\Models\User;

class EntryScorecardPolicy
{
    public function view(User $actor, EntryScorecard $scorecard): bool
    {
        $eventId = $scorecard->eventId();

        if ($eventId === null || ! $actor->isActive()) {
            return false;
        }

        return $actor->hasAdminAccess($eventId)
            || $actor->canScoreEntryScorecard($scorecard);
    }

    public function score(User $actor, EntryScorecard $scorecard): bool
    {
        return $actor->canScoreEntryScorecard($scorecard);
    }

    public function update(User $actor, EntryScorecard $scorecard): bool
    {
        return $this->score($actor, $scorecard);
    }
}
