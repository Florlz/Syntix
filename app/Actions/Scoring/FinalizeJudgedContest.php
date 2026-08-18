<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\Contest;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\JudgeScoreAggregationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class FinalizeJudgedContest
{
    public function __construct(
        private readonly ?JudgeScoreAggregationService $aggregation = null,
        private readonly ?AuditLogger $audit = null,
    ) {}

    public function handle(User $actor, Contest $contest): ResultSubmission
    {
        if (! $actor->canScoreContest($contest)) {
            throw new AuthorizationException('An assigned Tabulator is required to finalize this Contest.');
        }

        $contest = DB::transaction(function () use ($actor, $contest): Contest {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();
            if (! $actor->canScoreContest($contest)) {
                throw new AuthorizationException('An assigned Tabulator is required to finalize this Contest.');
            }
            if ($contest->state === ContestState::Completed && ($contest->result_payload['scoring_mode'] ?? null) === 'judged') {
                return $contest;
            }

            $result = ($this->aggregation ?? new JudgeScoreAggregationService)->aggregate($contest);
            if (! $result['readiness']['ready']) {
                throw new \DomainException('Judged finalization blocked: '.implode(', ', $result['readiness']['blocker_codes']).'.');
            }

            $ranked = collect($result['entries'])->sortBy('rank')->values()->map(fn (array $row): array => [
                'entry_id' => (int) $row['entry_id'],
                'entry' => $row['entry'],
                'delegation' => $row['delegation'],
                'rank' => $row['rank'],
                'scorecards' => $row['scorecards'],
                'aggregate_raw_total' => $row['aggregate_raw_total'],
                'adjustments' => $row['adjustments'],
                'adjustment_total' => $row['adjustment_total'],
                'final_total' => $row['final_total'],
            ])->all();
            $rule = $contest->ruleVersion()->firstOrFail();
            $payload = [
                'scoring_mode' => 'judged',
                'outcome_type' => 'played',
                'rule_version_id' => $rule->getKey(),
                'aggregation_method' => $result['aggregation_method'],
                'aggregation_confirmation' => $rule->aggregationConfirmation(),
                'winner_entry_id' => $ranked[0]['entry_id'],
                'ranked_entries' => $ranked,
                'tie_resolution' => $result['tie_resolution'],
                'tie_resolutions' => $result['tie_resolutions'],
                'source_reference' => $rule->source_reference,
                'finalized_by' => $actor->getKey(),
                'finalized_at' => now()->toIso8601String(),
            ];

            $contest->update([
                'state' => ContestState::Completed,
                'result_payload' => $payload,
                'revision' => ((int) $contest->revision) + 1,
                'completed_at' => now(),
                'completed_by' => $actor->getKey(),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor, AuditAction::JudgedContestFinalized, $contest,
                event: $contest->division?->competition?->event,
                after: ['ranked_entry_count' => count($ranked), 'winner_entry_id' => $payload['winner_entry_id']],
            );

            return $contest->fresh();
        });

        return (new SubmitContestResult($this->audit))->handle($actor, $contest);
    }
}
