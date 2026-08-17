<?php

namespace App\Services;

use App\Enums\RuleVersionState;
use App\Enums\ScorecardState;
use App\Models\Contest;
use App\Models\EntryScorecard;

final class JudgeScoreAggregationService
{
    /** @return array<string, mixed> */
    public function aggregate(Contest $contest): array
    {
        $contest = Contest::query()->whereKey($contest->getKey())->firstOrFail();
        $contest->load([
            'entries.entry.delegation',
            'scorecards.judge',
            'scorecards.values.criterion',
            'ruleVersion',
            'adjustments.recorder',
            'tieResolutions.resolver',
        ]);
        $rule = $contest->ruleVersion;
        $blockers = [];
        $missing = [];

        if (! $contest->isJudgingPanelLocked()) $blockers[] = 'judging_panel_unlocked';
        if ($rule === null) $blockers[] = 'rule_version_missing';
        if ($rule?->source_status === 'blocked') $blockers[] = 'source_blocked';
        if ($rule !== null && $rule->lifecycleState() !== RuleVersionState::Frozen) $blockers[] = 'rule_not_frozen';
        if ($rule !== null && ! $rule->hasConfirmedAggregation()) $blockers[] = 'aggregation_confirmation_missing';
        $deduction = $rule?->deduction_configuration ?? [];
        if (($deduction['code'] ?? null) !== null && ($deduction['calculation_status'] ?? null) !== 'authorized') {
            $blockers[] = 'adjustment_calculation_unauthorized';
        }

        $judgeIds = $contest->scorecards->pluck('judge_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        if ($judgeIds->isEmpty()) $blockers[] = 'panel_empty';
        $calculationScale = (int) ($rule?->calculation_scale ?? 4);
        $comparisonScale = (int) ($rule?->comparison_scale ?? $calculationScale);
        $roundingMode = (string) ($rule?->rounding_mode?->value ?? $rule?->rounding_mode ?? 'none');

        $rows = $contest->entries->sortBy('slot')->values()->map(function ($contestEntry) use (
            $contest, $rule, $judgeIds, $calculationScale, $comparisonScale, $roundingMode, &$missing,
        ): array {
            $cards = $contest->scorecards
                ->where('entry_id', $contestEntry->entry_id)
                ->sortBy('judge_id')
                ->values();
            $cardDtos = $judgeIds->map(function (int $judgeId) use ($cards, $contest, $contestEntry, &$missing): array {
                /** @var EntryScorecard|null $card */
                $card = $cards->first(fn (EntryScorecard $item): bool => (int) $item->judge_id === $judgeId);
                $complete = $card !== null
                    && in_array($card->scorecardState(), [ScorecardState::Submitted, ScorecardState::Approved], true)
                    && $card->calculated_total !== null;
                if ($card !== null && (int) $card->competition_rule_version_id !== (int) $contest->competition_rule_version_id) {
                    $complete = false;
                }
                if (! $complete) {
                    $missing[] = ['entry_id' => (int) $contestEntry->entry_id, 'judge_id' => $judgeId, 'scorecard_id' => $card?->getKey()];
                }

                return [
                    'scorecard_id' => $card === null ? null : (string) $card->getKey(),
                    'judge_id' => $judgeId,
                    'judge' => $card?->judge?->name,
                    'state' => $card?->scorecardState()->value ?? 'missing',
                    'revision' => $card?->revision,
                    'raw_total' => $card?->calculated_total === null ? null : (string) $card->calculated_total,
                    'criteria' => $card?->values->map(fn ($value): array => [
                        'criterion_id' => (int) $value->scoring_criterion_id,
                        'criterion' => $value->criterion?->name,
                        'raw_value' => (string) $value->raw_value,
                        'weighted_value' => (string) $value->weighted_value,
                    ])->values()->all() ?? [],
                ];
            })->all();

            $completeTotals = collect($cardDtos)->pluck('raw_total')->filter(fn ($value): bool => $value !== null)->values();
            $aggregate = null;
            if ($completeTotals->count() === $judgeIds->count() && $judgeIds->isNotEmpty() && $rule?->judge_aggregation_method === 'average') {
                $sum = $completeTotals->sum(fn (string $value): int => DecimalMath::toScaled($value, $calculationScale));
                $aggregate = DecimalMath::fromScaled(
                    DecimalMath::divideRounded($sum, $completeTotals->count(), 0, $roundingMode),
                    $calculationScale,
                );
            }

            $adjustments = $contest->adjustments->where('entry_id', $contestEntry->entry_id)->values();
            $adjustmentScaled = $adjustments->sum(fn ($item): int => DecimalMath::toScaled((string) $item->points, $calculationScale));
            $adjustmentTotal = DecimalMath::fromScaled($adjustmentScaled, $calculationScale);
            $final = $aggregate === null ? null : DecimalMath::fromScaled(
                DecimalMath::toScaled($aggregate, $calculationScale) - $adjustmentScaled,
                $calculationScale,
            );

            return [
                'entry_id' => (string) $contestEntry->entry_id,
                'entry' => $contestEntry->entry?->name,
                'delegation' => $contestEntry->entry?->delegation?->name,
                'scorecards' => $cardDtos,
                'aggregate_raw_total' => $aggregate,
                'adjustment_total' => $adjustmentTotal,
                'final_total' => $final,
                'comparison_total' => $final === null ? null : DecimalMath::fromScaled(DecimalMath::toScaled($final, $comparisonScale), $comparisonScale),
                'adjustments' => $adjustments->map(fn ($item): array => [
                    'id' => (string) $item->getKey(),
                    'code' => $item->code,
                    'label' => $item->label,
                    'source_reference' => $item->source_reference,
                    'input_value' => (string) $item->input_value,
                    'input_unit' => $item->input_unit,
                    'points' => (string) $item->points,
                    'notes' => $item->notes,
                    'recorded_by' => $item->recorder?->name,
                    'recorded_at' => $item->recorded_at?->toIso8601String(),
                ])->all(),
            ];
        })->all();

        if ($missing !== []) $blockers[] = 'missing_scorecards';
        if ($rule !== null && $contest->scorecards->contains(fn (EntryScorecard $card): bool => (int) $card->competition_rule_version_id !== (int) $rule->getKey())) {
            $blockers[] = 'scorecard_rule_mismatch';
        }
        if (($deduction['code'] ?? null) !== null && ($deduction['calculation_status'] ?? null) === 'authorized') {
            $missingAdjustmentEntries = $contest->entries->pluck('entry_id')->diff(
                $contest->adjustments->where('code', $deduction['code'])->pluck('entry_id'),
            );
            if ($missingAdjustmentEntries->isNotEmpty()) $blockers[] = 'adjustment_evidence_missing';
        }
        if ($rule !== null && $rule->judge_aggregation_method !== 'average') $blockers[] = 'aggregation_method_unsupported';

        $rankable = collect($rows)->filter(fn (array $row): bool => $row['comparison_total'] !== null)
            ->sortByDesc(fn (array $row): int => DecimalMath::toScaled($row['comparison_total'], $comparisonScale))
            ->values();
        $rank = 0;
        $previous = null;
        foreach ($rankable as $index => $row) {
            if ($previous !== $row['comparison_total']) $rank = $index + 1;
            $rows[array_search($row['entry_id'], array_column($rows, 'entry_id'), true)]['rank'] = $rank;
            $previous = $row['comparison_total'];
        }

        $ties = $rankable->groupBy('comparison_total')->filter(fn ($group): bool => $group->count() > 1)
            ->map(fn ($group, string $total): array => [
                'comparison_total' => $total,
                'entry_ids' => $group->pluck('entry_id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            ])->values()->all();
        $resolutionFor = function (array $tie) use ($contest) {
            return $contest->tieResolutions->sortByDesc('resolved_at')->first(function ($item) use ($tie): bool {
                $resolved = collect($item->tied_entry_ids)->map(fn ($id): int => (int) $id)->sort()->values()->all();
                return $tie['entry_ids'] === $resolved && (string) $item->comparison_total === (string) $tie['comparison_total'];
            });
        };
        $resolutions = collect($ties)->map($resolutionFor)->filter()->values();
        if (count($ties) !== $resolutions->count()) {
            $blockers[] = 'tie_resolution_required';
        } else {
            foreach ($resolutions as $resolution) {
                $baseRank = collect($rows)
                    ->whereIn('entry_id', collect($resolution->authorized_order)->map(fn ($id): string => (string) $id))
                    ->min('rank') ?? 1;
                foreach ($resolution->authorized_order as $offset => $entryId) {
                    $rowIndex = array_search((string) $entryId, array_column($rows, 'entry_id'), true);
                    if ($rowIndex !== false) $rows[$rowIndex]['rank'] = $baseRank + $offset;
                }
            }
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'contest_id' => (string) $contest->getKey(),
            'rule_version_id' => $rule?->getKey(),
            'aggregation_method' => $rule?->judge_aggregation_method,
            'entries' => $rows,
            'ties' => $ties,
            'tie_resolution' => $resolutions->first() === null ? null : $this->resolutionDto($resolutions->first()),
            'tie_resolutions' => $resolutions->map(fn ($resolution): array => $this->resolutionDto($resolution))->all(),
            'readiness' => [
                'ready' => $blockers === [],
                'blocker_codes' => $blockers,
                'missing_scorecards' => $missing,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function resolutionDto($resolution): array
    {
        return [
            'id' => (string) $resolution->getKey(), 'tied_entry_ids' => $resolution->tied_entry_ids,
            'authorized_order' => $resolution->authorized_order, 'reason' => $resolution->reason,
            'reference' => $resolution->reference, 'resolved_by' => $resolution->resolver?->name,
            'resolved_at' => $resolution->resolved_at?->toIso8601String(),
        ];
    }
}
