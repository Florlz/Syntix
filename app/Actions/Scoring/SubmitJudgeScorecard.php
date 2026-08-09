<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\RuleVersionState;
use App\Enums\ScorecardState;
use App\Models\EntryScorecard;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SubmitJudgeScorecard
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, EntryScorecard $scorecard): EntryScorecard
    {
        if (! $actor->canScoreEntryScorecard($scorecard)) {
            throw new AuthorizationException('The Judge is not assigned to this scorecard.');
        }

        return DB::transaction(function () use ($actor, $scorecard): EntryScorecard {
            $scorecard = EntryScorecard::query()
                ->with('ruleVersion.criteria')
                ->whereKey($scorecard->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($scorecard->scorecardState(), [ScorecardState::Draft, ScorecardState::Rejected], true)) {
                throw new \DomainException('Only draft or rejected scorecards can be submitted.');
            }

            if ($scorecard->ruleVersion?->lifecycleState() !== RuleVersionState::Frozen) {
                throw new \DomainException('Scorecard submission requires its frozen rule version.');
            }

            if ($scorecard->judge_id !== null && (int) $scorecard->judge_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('A scorecard belongs to another Judge.');
            }

            $requiredIds = $scorecard->ruleVersion->criteria
                ->where('is_required', true)
                ->pluck('id');
            $storedIds = $scorecard->values()->pluck('scoring_criterion_id');

            if ($requiredIds->diff($storedIds)->isNotEmpty()) {
                throw new \DomainException('Every required criterion must have a score before submission.');
            }

            $scorecard->update([
                'state' => ScorecardState::Submitted,
                'submitted_at' => now(),
                'revision' => ((int) $scorecard->revision) + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ResultSubmitted,
                $scorecard,
                after: [
                    'state' => ScorecardState::Submitted->value,
                    'revision' => $scorecard->revision,
                ],
            );

            return $scorecard->fresh(['values']);
        });
    }
}
