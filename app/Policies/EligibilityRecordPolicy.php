<?php

namespace App\Policies;

use App\Models\EligibilityRecord;
use App\Models\User;

class EligibilityRecordPolicy
{
    public function view(User $actor, EligibilityRecord $record): bool
    {
        return $actor->hasAdminAccess($record->event_id);
    }

    public function update(User $actor, EligibilityRecord $record): bool
    {
        return $this->view($actor, $record);
    }

    public function delete(User $actor, EligibilityRecord $record): bool
    {
        return false;
    }
}
