<?php

namespace App\Policies;

use App\Enums\ResultSubmissionState;
use App\Models\ResultSubmission;
use App\Models\User;

class ResultSubmissionPolicy
{
    public function view(User $actor, ResultSubmission $submission): bool
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        return $event !== null
            && $actor->isActive()
            && ($actor->hasAdminAccess($event)
                || ($submission->submitted_by === $actor->getKey()
                    && $actor->canScoreContest($submission->contest)));
    }

    public function approve(User $actor, ResultSubmission $submission): bool
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        return $event !== null
            && $submission->submissionState() === ResultSubmissionState::Submitted
            && $actor->hasAdminAccess($event);
    }

    public function reject(User $actor, ResultSubmission $submission): bool
    {
        return $this->approve($actor, $submission);
    }
}
