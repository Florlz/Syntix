<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\Contest;
use App\Models\Entry;
use App\Models\ScoringAdjustment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScoringAdjustmentCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RecordScoringAdjustment
{
    public function __construct(
        private readonly ?ScoringAdjustmentCalculator $calculator = null,
        private readonly ?AuditLogger $audit = null,
    ) {}

    public function handle(
        User $actor,
        Contest $contest,
        Entry $entry,
        string $code,
        string $inputValue,
        string $inputUnit,
        ?string $notes = null,
    ): ScoringAdjustment {
        if (! $actor->canScoreContest($contest)) {
            throw new AuthorizationException('An assigned Tabulator is required to record an adjustment.');
        }

        return DB::transaction(function () use ($actor, $contest, $entry, $code, $inputValue, $inputUnit, $notes): ScoringAdjustment {
            $contest = Contest::query()->with('ruleVersion')->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();
            if (! $actor->canScoreContest($contest)) {
                throw new AuthorizationException('An assigned Tabulator is required to record an adjustment.');
            }
            if ($contest->state === ContestState::Completed) {
                throw new \DomainException('Adjustments are immutable after judged finalization. Reopen the result for correction first.');
            }
            if (! $contest->entries()->where('entry_id', $entry->getKey())->exists()) {
                throw new \DomainException('The adjustment entry must participate in this Contest.');
            }

            $rule = $contest->ruleVersion;
            if ($rule === null || $rule->source_status === 'blocked') {
                throw new \DomainException('A source-confirmed rule is required for adjustments.');
            }

            if (ScoringAdjustment::query()
                ->where('contest_id', $contest->getKey())
                ->where('entry_id', $entry->getKey())
                ->where('code', $code)
                ->lockForUpdate()
                ->exists()) {
                throw new \DomainException('void the active adjustment before recording its replacement.');
            }

            $points = ($this->calculator ?? new ScoringAdjustmentCalculator)->calculate(
                $rule->deduction_configuration ?? [], trim($code), trim($inputValue), trim($inputUnit),
            );

            $adjustment = ScoringAdjustment::create([
                'contest_id' => $contest->getKey(),
                'entry_id' => $entry->getKey(),
                'competition_rule_version_id' => $rule->getKey(),
                'code' => trim($code),
                'label' => match (trim($code)) {
                    'performance_time' => 'Performance time penalty',
                    'word_count' => 'Word-count penalty',
                    default => trim($code),
                },
                'source_reference' => (string) $rule->source_reference,
                'input_value' => trim($inputValue),
                'input_unit' => trim($inputUnit),
                'points' => $points,
                'notes' => $notes,
                'recorded_by' => $actor->getKey(),
                'recorded_at' => now(),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor, AuditAction::ScoringAdjustmentRecorded, $adjustment,
                after: ['contest_id' => $contest->getKey(), 'entry_id' => $entry->getKey(), 'code' => $code, 'points' => $points],
            );

            return $adjustment->fresh();
        });
    }
}
