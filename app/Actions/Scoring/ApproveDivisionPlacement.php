<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\DivisionPlacementState;
use App\Enums\EventRole;
use App\Enums\LedgerEntryType;
use App\Enums\RuleVersionState;
use App\Models\DivisionPlacement;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ApproveDivisionPlacement
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, DivisionPlacement $placement, ?string $reason = null): DivisionPlacement
    {
        $placement->loadMissing('division.competition.event');
        $event = $placement->division?->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can approve a Division Placement.');
        }

        return DB::transaction(function () use ($actor, $placement, $reason, $event): DivisionPlacement {
            $placement = DivisionPlacement::query()
                ->with(['division.competition', 'ruleVersion.pointTemplate.rules', 'items'])
                ->whereKey($placement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($placement->placementState() !== DivisionPlacementState::Submitted) {
                throw new \DomainException('Only submitted Division Placements can be approved.');
            }

            if ($placement->ruleVersion?->lifecycleState() !== RuleVersionState::Frozen) {
                throw new \DomainException('Placement approval requires the frozen rule version bound to the placement.');
            }

            $current = DivisionPlacement::query()
                ->where('competition_division_id', $placement->competition_division_id)
                ->where('state', DivisionPlacementState::Approved->value)
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                throw new \DomainException('The Division already has an approved final placement.');
            }

            $placement->update([
                'state' => DivisionPlacementState::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'reason' => $reason,
            ]);

            foreach ($placement->items as $item) {
                if (! self::hasValue((string) $item->championship_points)) {
                    continue;
                }

                $ledger = ScoreLedgerEntry::create([
                    'event_id' => $event->getKey(),
                    'event_delegation_id' => $item->event_delegation_id,
                    'division_placement_id' => $placement->getKey(),
                    'division_placement_item_id' => $item->getKey(),
                    'entry_type' => LedgerEntryType::Award,
                    'amount' => $item->championship_points,
                    'source_key' => "division-placement:{$placement->getKey()}:item:{$item->getKey()}:award",
                    'source_revision' => $placement->revision,
                    'created_by' => $actor->getKey(),
                    'committed_at' => now(),
                    'reason' => $reason,
                ]);

                ($this->audit ?? new AuditLogger)->record(
                    $actor,
                    AuditAction::LedgerEntryCommitted,
                    $ledger,
                    event: $event,
                    reason: $reason,
                    after: [
                        'entry_type' => LedgerEntryType::Award->value,
                        'amount' => (string) $item->championship_points,
                        'source_key' => $ledger->source_key,
                    ],
                );
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::DivisionPlacementApproved,
                $placement,
                event: $event,
                reason: $reason,
                after: [
                    'state' => DivisionPlacementState::Approved->value,
                    'ledger_entries_created' => $placement->items->filter(
                        fn ($item): bool => self::hasValue((string) $item->championship_points)
                    )->count(),
                ],
            );

            return $placement->fresh(['items', 'ledgerEntries']);
        });
    }

    private static function hasValue(string $amount): bool
    {
        return (float) $amount !== 0.0;
    }
}
