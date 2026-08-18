<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ResultSubmissionState;
use App\Models\ResultSubmission;
use App\Models\EntryScorecard;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RejectContestResult
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /** @param list<int> $scorecardIds */
    public function handle(User $actor, ResultSubmission $submission, string $reason, array $scorecardIds = []): ResultSubmission
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can reject a result.');

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($actor, $submission, $reason, $event, $scorecardIds): ResultSubmission {
            $submission = ResultSubmission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();

            if ($submission->submissionState() !== ResultSubmissionState::Submitted) {
                throw new \DomainException('Only submitted results can be rejected.');
            }

            $submission->update([
                'state' => ResultSubmissionState::Rejected,
                'rejection_reason' => trim($reason),
            ]);

            if (($submission->payload['scoring_mode'] ?? null) === 'judged' && $scorecardIds !== []) {
                $snapshotIds = collect($submission->payload['ranked_entries'] ?? [])
                    ->flatMap(fn (array $entry): array => collect($entry['scorecards'] ?? [])->pluck('scorecard_id')->filter()->map(fn ($id): int => (int) $id)->all())
                    ->unique()->values();
                $requested = collect($scorecardIds)->map(fn ($id): int => (int) $id)->unique()->values();
                if ($requested->diff($snapshotIds)->isNotEmpty()) {
                    throw new \DomainException('A selected scorecard is not part of this judged result snapshot.');
                }
                EntryScorecard::query()->where('contest_id', $submission->contest_id)->whereKey($requested)->update([
                    'state' => 'rejected',
                    'approved_at' => null,
                    'submitted_at' => null,
                    'rejection_reason' => trim($reason),
                    'revision' => DB::raw('revision + 1'),
                    'updated_at' => now(),
                ]);
            }

            (new ReopenContestForCorrection($this->audit))->handle($actor, $submission, $reason);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ResultRejected,
                $submission,
                event: $event,
                reason: trim($reason),
                after: ['state' => ResultSubmissionState::Rejected->value],
            );

            return $submission->fresh();
        });
    }
}
