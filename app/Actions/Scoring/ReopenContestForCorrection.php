<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\Contest;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ReopenContestForCorrection
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, ResultSubmission $submission, string $reason): Contest
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can reopen a result for correction.');

        return DB::transaction(function () use ($actor, $submission, $reason, $event): Contest {
            $contest = Contest::query()->whereKey($submission->contest_id)->lockForUpdate()->firstOrFail();
            $before = ['state' => $contest->state->value, 'revision' => (int) $contest->revision];

            if ($contest->state !== ContestState::Completed) {
                throw new \DomainException('Only a completed contest can be reopened for correction.');
            }

            $contest->update([
                'state' => ContestState::Live,
                'live_payload' => $submission->payload ?? $contest->live_payload ?? [],
                'completed_at' => null,
                'completed_by' => null,
                'revision' => ((int) $contest->revision) + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestReopenedForCorrection,
                $contest,
                event: $event,
                reason: trim($reason),
                before: $before,
                after: ['state' => ContestState::Live->value, 'revision' => $contest->revision],
            );

            return $contest->fresh();
        });
    }
}
