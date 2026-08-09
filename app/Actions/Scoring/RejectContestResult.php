<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ResultSubmissionState;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RejectContestResult
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, ResultSubmission $submission, string $reason): ResultSubmission
    {
        $submission->loadMissing('contest.division.competition.event');
        $event = $submission->contest?->division?->competition?->event;

        if ($event === null || ! $actor->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the active Global Admin can reject a result.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($actor, $submission, $reason, $event): ResultSubmission {
            $submission = ResultSubmission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();

            if ($submission->submissionState() !== ResultSubmissionState::Submitted) {
                throw new \DomainException('Only submitted results can be rejected.');
            }

            $submission->update([
                'state' => ResultSubmissionState::Rejected,
                'rejection_reason' => trim($reason),
            ]);

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
