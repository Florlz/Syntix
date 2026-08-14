<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\OfficialOutcomeState;
use App\Enums\OutcomeType;
use App\Enums\ResultSubmissionState;
use App\Models\ContestEntry;
use App\Models\OfficialContestOutcome;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AutomaticPlacementDeriver;
use App\Services\BracketAdvancer;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ApproveContestOutcome
{
    public function __construct(
        private readonly ?AuditLogger $audit = null,
        private readonly ?BracketAdvancer $advancer = null,
        private readonly ?AutomaticPlacementDeriver $placementDeriver = null,
    ) {}

    public function handle(User $actor, ResultSubmission $submission, ?string $reason = null): OfficialContestOutcome
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can approve a contest outcome.');

        return DB::transaction(function () use ($actor, $submission, $reason, $event): OfficialContestOutcome {
            $submission = ResultSubmission::query()
                ->with('contest')
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($submission->submissionState() !== ResultSubmissionState::Submitted) {
                throw new \DomainException('Only submitted results can be approved.');
            }

            $payload = $submission->payload ?? [];
            $outcomeType = OutcomeType::tryFrom((string) ($payload['outcome_type'] ?? ''));

            if ($outcomeType === null) {
                throw new \DomainException('An approved outcome requires a configured outcome type.');
            }

            $winnerId = $payload['winner_entry_id'] ?? null;

            if ($winnerId !== null) {
                $belongsToContest = ContestEntry::query()
                    ->where('contest_id', $submission->contest_id)
                    ->where('entry_id', $winnerId)
                    ->exists();

                if (! $belongsToContest) {
                    throw new \DomainException('The outcome winner is not an entry in this contest.');
                }
            }

            $current = OfficialContestOutcome::query()
                ->where('contest_id', $submission->contest_id)
                ->where('state', OfficialOutcomeState::Approved->value)
                ->lockForUpdate()
                ->first();
            $revision = (int) OfficialContestOutcome::query()
                ->where('contest_id', $submission->contest_id)
                ->max('revision') + 1;

            $current?->update(['state' => OfficialOutcomeState::Superseded]);

            $outcome = OfficialContestOutcome::create([
                'contest_id' => $submission->contest_id,
                'result_submission_id' => $submission->getKey(),
                'revision' => $revision,
                'state' => OfficialOutcomeState::Approved,
                'outcome_type' => $outcomeType,
                'winner_entry_id' => $winnerId,
                'payload' => $payload,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'reason' => $reason,
            ]);

            $submission->update([
                'state' => ResultSubmissionState::Approved,
                'approved_at' => now(),
            ]);

            ($this->advancer ?? new BracketAdvancer)->apply($outcome);
            ($this->placementDeriver ?? new AutomaticPlacementDeriver)->derive($actor, $outcome);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestOutcomeApproved,
                $outcome,
                event: $event,
                reason: $reason,
                after: [
                    'state' => OfficialOutcomeState::Approved->value,
                    'revision' => $revision,
                    'championship_points_created' => false,
                ],
            );

            return $outcome;
        });
    }
}
