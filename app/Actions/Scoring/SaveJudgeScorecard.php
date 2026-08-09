<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\CriterionNumberMeaning;
use App\Enums\RuleVersionState;
use App\Enums\ScorecardState;
use App\Models\EntryScorecard;
use App\Models\ScoringCriterion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SaveJudgeScorecard
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<array{criterion_id: int, raw_value: string|int|float, deduction?: string|int|float, notes?: string}>  $values
     */
    public function handle(User $actor, EntryScorecard $scorecard, array $values, int $expectedRevision): EntryScorecard
    {
        if (! $actor->canScoreEntryScorecard($scorecard)) {
            throw new AuthorizationException('The Judge is not assigned to this scorecard.');
        }

        return DB::transaction(function () use ($actor, $scorecard, $values, $expectedRevision): EntryScorecard {
            $scorecard = EntryScorecard::query()
                ->with('contest.division.competition', 'ruleVersion.criteria')
                ->whereKey($scorecard->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($scorecard->scorecardState(), [ScorecardState::Draft, ScorecardState::Rejected], true)) {
                throw new \DomainException('Only draft or rejected scorecards can be edited.');
            }

            if ((int) $scorecard->revision !== $expectedRevision) {
                throw new \DomainException('The scorecard revision is stale.');
            }

            $version = $scorecard->ruleVersion;

            if ($version === null) {
                throw new \DomainException('Judge scoring requires a governing rule version.');
            }

            if ($version->lifecycleState() === RuleVersionState::ActivatedEditable) {
                $version->update([
                    'lifecycle_state' => RuleVersionState::Frozen,
                    'frozen_at' => now(),
                ]);
                $scorecard->contest?->division?->update(['scoring_started_at' => now()]);
            }

            if ($version->lifecycleState() !== RuleVersionState::Frozen) {
                throw new \DomainException('Judge scoring requires the frozen rule version bound to the scorecard.');
            }

            if ($scorecard->judge_id !== null && (int) $scorecard->judge_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('A scorecard belongs to another Judge.');
            }

            $criteria = $version->criteria->keyBy(fn (ScoringCriterion $criterion): int => (int) $criterion->getKey());
            $storedIds = [];
            $total = 0;
            $calculationScale = (int) ($version->calculation_scale ?? 0);

            foreach ($values as $value) {
                $criterionId = (int) ($value['criterion_id'] ?? 0);
                $criterion = $criteria->get($criterionId);

                if ($criterion === null) {
                    throw new \DomainException('Every score value must reference a criterion in the frozen rule version.');
                }

                if (in_array($criterionId, $storedIds, true)) {
                    throw new \DomainException('Each criterion can have only one score value.');
                }

                $rawScale = (int) ($criterion->input_scale ?? $version->input_scale ?? 0);
                $raw = (string) ($value['raw_value'] ?? '');
                $deduction = (string) ($value['deduction'] ?? '0');
                $rawScaled = DecimalMath::toScaled($raw, $rawScale);
                $deductionScaled = DecimalMath::toScaled($deduction, $rawScale);
                $minimum = $criterion->raw_minimum === null ? null : DecimalMath::toScaled((string) $criterion->raw_minimum, $rawScale);
                $maximum = $criterion->raw_maximum === null ? null : DecimalMath::toScaled((string) $criterion->raw_maximum, $rawScale);

                if (($minimum !== null && $rawScaled < $minimum) || ($maximum !== null && $rawScaled > $maximum)) {
                    throw new \DomainException("The score for {$criterion->name} is outside its configured range.");
                }

                if ($deductionScaled < 0 || $deductionScaled > $rawScaled) {
                    throw new \DomainException("The deduction for {$criterion->name} is outside its configured bounds.");
                }

                $netScaled = $rawScaled - $deductionScaled;
                $netValue = DecimalMath::fromScaled($netScaled, $rawScale);
                $rounding = (string) ($version->rounding_mode ?? 'none');

                $weightedValue = $criterion->numberMeaning() === CriterionNumberMeaning::PercentageWeight
                    ? DecimalMath::weightedPercent(
                        $netValue,
                        $rawScale,
                        (string) $criterion->weight,
                        4,
                        $calculationScale,
                        $rounding,
                    )
                    : DecimalMath::fromScaled(
                        DecimalMath::divideRounded($netScaled, 10 ** $rawScale, $calculationScale, $rounding),
                        $calculationScale,
                    );

                $total += DecimalMath::toScaled($weightedValue, $calculationScale);
                $storedIds[] = $criterionId;

                $scorecard->values()->updateOrCreate(
                    ['scoring_criterion_id' => $criterionId],
                    [
                        'raw_value' => $raw,
                        'deduction' => $deduction,
                        'net_value' => $netValue,
                        'weighted_value' => $weightedValue,
                        'notes' => $value['notes'] ?? null,
                    ],
                );
            }

            $scorecard->values()
                ->when($storedIds !== [], fn ($query) => $query->whereNotIn('scoring_criterion_id', $storedIds))
                ->delete();

            $scorecard->update([
                'judge_id' => $actor->getKey(),
                'competition_rule_version_id' => $version->getKey(),
                'state' => ScorecardState::Draft,
                'calculated_total' => DecimalMath::fromScaled($total, $calculationScale),
                'revision' => $expectedRevision + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestScoreRecorded,
                $scorecard,
                before: ['revision' => $expectedRevision],
                after: [
                    'revision' => $scorecard->revision,
                    'value_count' => count($storedIds),
                    'calculated_total' => (string) $scorecard->calculated_total,
                ],
            );

            return $scorecard->fresh(['values']);
        });
    }
}
