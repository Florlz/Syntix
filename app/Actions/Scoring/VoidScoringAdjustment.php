<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\ScoringAdjustment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class VoidScoringAdjustment
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, ScoringAdjustment $adjustment, string $reason): ScoringAdjustment
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to void an adjustment.');
        }

        return DB::transaction(function () use ($actor, $adjustment, $reason): ScoringAdjustment {
            $adjustment = ScoringAdjustment::withVoided()->with('contest')->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
            if (! $actor->canScoreContest($adjustment->contest)) {
                throw new AuthorizationException('An assigned Tabulator is required to void an adjustment.');
            }
            if ($adjustment->contest->state === ContestState::Completed) {
                throw new \DomainException('Adjustments are immutable after judged finalization. Reopen the result for correction first.');
            }
            if ($adjustment->isVoided()) {
                throw new \DomainException('The adjustment is already voided.');
            }

            $adjustment->update([
                'voided_by' => $actor->getKey(),
                'voided_at' => now(),
                'void_reason' => trim($reason),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor, AuditAction::ScoringAdjustmentVoided, $adjustment,
                after: ['void_reason' => trim($reason)], reason: trim($reason),
            );

            return ScoringAdjustment::withVoided()->findOrFail($adjustment->getKey());
        });
    }
}
