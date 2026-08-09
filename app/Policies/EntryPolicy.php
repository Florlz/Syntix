<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;

class EntryPolicy
{
    public function view(User $actor, Entry $entry): bool
    {
        return $entry->eventId() !== null && $actor->hasAdminAccess($entry->eventId());
    }

    public function create(User $actor): bool
    {
        return $actor->isGlobalAdmin();
    }

    public function update(User $actor, Entry $entry): bool
    {
        return $this->view($actor, $entry);
    }

    public function delete(User $actor, Entry $entry): bool
    {
        return false;
    }
}
