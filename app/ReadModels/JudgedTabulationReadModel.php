<?php

namespace App\ReadModels;

use App\Enums\ContestState;
use App\Enums\ResultSubmissionState;
use App\Models\Contest;
use App\Models\ScoringAdjustment;
use App\Services\JudgeScoreAggregationService;
use Illuminate\Support\Collection;

final class JudgedTabulationReadModel
{
    public function __construct(private readonly ?JudgeScoreAggregationService $aggregation = null) {}

    /** @return array<string, mixed> */
    public function forContest(Contest $contest): array
    {
        $matrix = ($this->aggregation ?? new JudgeScoreAggregationService)->aggregate($contest);
        $contest->loadMissing('resultSubmissions');
        $history = ScoringAdjustment::withVoided()
            ->where('contest_id', $contest->getKey())
            ->with(['recorder', 'voider'])
            ->get()
            ->groupBy('entry_id');

        foreach ($matrix['entries'] as &$entry) {
            /** @var Collection<int, ScoringAdjustment> $adjustments */
            $adjustments = $history->get((int) $entry['entry_id'], collect());
            $entry['adjustment_history'] = $adjustments->map(fn (ScoringAdjustment $item): array => $this->adjustmentDto($item))->values()->all();
        }
        unset($entry);

        $submission = $contest->resultSubmissions->sortByDesc('id')->first();
        $matrix['submission'] = $submission === null ? null : [
            'id' => (string) $submission->getKey(),
            'state' => $submission->submissionState()->value,
            'contest_revision' => (int) $submission->contest_revision,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'approved_at' => $submission->approved_at?->toIso8601String(),
            'rejection_reason' => $submission->rejection_reason,
        ];
        $matrix['operational_state'] = $this->operationalState($contest, $matrix['readiness'], $submission?->submissionState());

        return $matrix;
    }

    /** @return array<string, mixed> */
    private function adjustmentDto(ScoringAdjustment $item): array
    {
        return [
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
            'status' => $item->isVoided() ? 'voided' : 'active',
            'voided_by' => $item->voider?->name,
            'voided_at' => $item->voided_at?->toIso8601String(),
            'void_reason' => $item->void_reason,
        ];
    }

    private function operationalState(Contest $contest, array $readiness, ?ResultSubmissionState $submission): string
    {
        return match ($submission) {
            ResultSubmissionState::Approved => 'approved',
            ResultSubmissionState::Submitted => 'submitted',
            ResultSubmissionState::Rejected => 'waiting',
            default => $this->stateFromReadiness($contest, $readiness),
        };
    }

    private function stateFromReadiness(Contest $contest, array $readiness): string
    {
        $codes = $readiness['blocker_codes'] ?? [];
        if (in_array('adjustment_evidence_missing', $codes, true)) return 'adjustment_required';
        if (in_array('tie_resolution_required', $codes, true)) return 'tie';
        if ($readiness['ready'] ?? false) return 'ready';
        if ($contest->state === ContestState::Completed) return 'completed';

        return 'waiting';
    }
}
