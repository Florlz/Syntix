<?php

namespace App\Policies;

use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\ScoringAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ScoringAssignmentPolicy
{
    public function create(
        User $actor,
        Event $event,
        User $assignee,
        ScoringAssignmentScope|string $scope,
        Model $target,
    ): bool {
        $scope = $scope instanceof ScoringAssignmentScope ? $scope : ScoringAssignmentScope::from($scope);

        if (! $actor->hasActiveEventRole($event, EventRole::Admin)
            || ! $assignee->isActive()
            || $event->isArchived()
            || ! $target->exists
            || ScoringAssignment::eventIdForTarget($target) !== (int) $event->getKey()) {
            return false;
        }

        return match ($scope) {
            ScoringAssignmentScope::EntryScorecard => $target instanceof EntryScorecard
                && $assignee->hasActiveEventRole($event, EventRole::Judge),
            ScoringAssignmentScope::CompetitionDivision => $target instanceof Division
                && $assignee->hasActiveEventRole($event, EventRole::Tabulator),
            ScoringAssignmentScope::Contest => $target instanceof Contest
                && $assignee->hasActiveEventRole($event, EventRole::Tabulator),
        };
    }

    public function revoke(User $actor, ScoringAssignment $assignment): bool
    {
        return $assignment->isActive()
            && $actor->hasActiveEventRole($assignment->event_id, EventRole::Admin);
    }
}
