<?php

namespace App\Policies;

use App\Models\ScoreLedgerEntry;
use App\Models\User;

class ScoreLedgerEntryPolicy
{
    public function view(User $actor, ScoreLedgerEntry $entry): bool
    {
        return $actor->isActive()
            && $actor->hasAdminAccess($entry->event_id);
    }
}
