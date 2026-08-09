<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\ScoreLedgerEntry;
use App\Models\User;

class ScoreLedgerEntryPolicy
{
    public function view(User $actor, ScoreLedgerEntry $entry): bool
    {
        return $actor->isActive()
            && $actor->hasActiveEventRole($entry->event_id, EventRole::Admin);
    }
}
