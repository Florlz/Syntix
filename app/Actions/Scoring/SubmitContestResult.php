<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Enums\ResultSubmissionState;
use App\Models\Contest;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\GlobalAdminNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SubmitContestResult
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, Contest $contest): ResultSubmission
    {
        if (! $actor->canScoreContest($contest)) {
            throw new AuthorizationException('The Tabulator is not assigned to this contest.');
        }

        $created = false;
        $submission = DB::transaction(function () use ($actor, $contest, &$created): ResultSubmission {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();

            if ($contest->state !== ContestState::Completed) {
                throw new \DomainException('Only completed contests can be submitted.');
            }

            $existing = $contest->resultSubmissions()
                ->whereIn('state', [
                    ResultSubmissionState::Submitted->value,
                    ResultSubmissionState::Approved->value,
                ])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $submission = $contest->resultSubmissions()->create([
                'submitted_by' => $actor->getKey(),
                'state' => ResultSubmissionState::Submitted,
                'contest_revision' => $contest->revision,
                'payload' => $contest->result_payload ?? [],
                'submitted_at' => now(),
            ]);
            $created = true;

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ResultSubmitted,
                $submission,
                event: $contest->division?->competition?->event,
                after: [
                    'state' => ResultSubmissionState::Submitted->value,
                    'contest_revision' => $contest->revision,
                ],
            );

            return $submission;
        });

        if ($created) {
            GlobalAdminNotifier::resultSubmitted($submission);
        }

        return $submission;
    }
}
