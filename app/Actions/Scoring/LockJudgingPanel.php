<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\RuleVersionState;
use App\Models\Contest;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Support\Facades\DB;

final class LockJudgingPanel
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, Contest $contest): Contest
    {
        return DB::transaction(function () use ($actor, $contest): Contest {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();
            $contest->load('division.competition.event', 'entries', 'scorecards', 'ruleVersion');
            $event = Event::query()->whereKey($contest->eventId())->lockForUpdate()->firstOrFail();
            EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can lock a judging panel.');

            if ($contest->isJudgingPanelLocked()) {
                return $contest;
            }

            $rule = $contest->ruleVersion;
            if ($rule === null || $rule->source_status === 'blocked') {
                throw new \DomainException('A valid unblocked scoring rule is required.');
            }
            if (! $rule->hasConfirmedAggregation()) {
                throw new \DomainException('Confirm the Judge aggregation method and authority before locking the panel.');
            }
            $deduction = $rule->deduction_configuration ?? [];
            if (($deduction['code'] ?? null) !== null && ($deduction['calculation_status'] ?? null) !== 'authorized') {
                throw new \DomainException('Authorize the source deduction calculation before locking the panel.');
            }

            if ($contest->scorecards->contains(fn ($scorecard): bool => (int) $scorecard->competition_rule_version_id !== (int) $rule->getKey())) {
                throw new \DomainException('Every scorecard must use the Contest rule version before panel locking.');
            }

            $entryIds = $contest->entries->pluck('entry_id')->map(fn ($id): int => (int) $id)->sort()->values();
            $judgeIds = $contest->scorecards->pluck('judge_id')->filter()->unique()->sort()->values();
            if ($entryIds->isEmpty() || $judgeIds->isEmpty()) {
                throw new \DomainException('At least one entry and one Judge are required.');
            }

            foreach ($entryIds as $entryId) {
                $entryJudges = $contest->scorecards
                    ->where('entry_id', $entryId)
                    ->pluck('judge_id')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();
                if ($entryJudges->all() !== $judgeIds->all()) {
                    throw new \DomainException('Every participating entry must have the same Judge panel.');
                }
            }

            if ($contest->scorecards->count() !== $entryIds->count() * $judgeIds->count()) {
                throw new \DomainException('Duplicate or incomplete Judge scorecards prevent panel locking.');
            }

            if ($rule->lifecycleState() === RuleVersionState::ActivatedEditable) {
                $rule->update([
                    'lifecycle_state' => RuleVersionState::Frozen,
                    'frozen_at' => now(),
                ]);
            } elseif ($rule->lifecycleState() !== RuleVersionState::Frozen) {
                throw new \DomainException('The scoring rule must be active before panel locking.');
            }

            $contest->update([
                'judging_panel_locked_at' => now(),
                'judging_panel_locked_by' => $actor->getKey(),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::JudgingPanelLocked,
                $contest,
                $event,
                after: ['judge_ids' => $judgeIds->all(), 'entry_ids' => $entryIds->all()],
            );

            return $contest->fresh(['scorecards', 'entries', 'ruleVersion']);
        });
    }
}
