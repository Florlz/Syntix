<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\DivisionPlacementState;
use App\Enums\EventRole;
use App\Enums\LedgerEntryType;
use App\Models\DivisionPlacement;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class VoidDivisionPlacement
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, DivisionPlacement $placement, string $reason): DivisionPlacement
    {
        $placement->loadMissing('division.competition.event', 'items');
        $event = $placement->division?->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can void a Division Placement.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A void reason is required.');
        }

        return DB::transaction(function () use ($actor, $placement, $reason, $event): DivisionPlacement {
            $placement = DivisionPlacement::query()
                ->with('items')
                ->whereKey($placement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($placement->placementState() !== DivisionPlacementState::Approved) {
                throw new \DomainException('Only an approved Division Placement can be voided.');
            }

            $placement->update([
                'state' => DivisionPlacementState::Voided,
                'reason' => trim($reason),
            ]);

            foreach ($placement->items as $item) {
                if ((float) $item->championship_points === 0.0) {
                    continue;
                }

                ScoreLedgerEntry::create([
                    'event_id' => $event->getKey(),
                    'event_delegation_id' => $item->event_delegation_id,
                    'division_placement_id' => $placement->getKey(),
                    'division_placement_item_id' => $item->getKey(),
                    'entry_type' => LedgerEntryType::Reversal,
                    'amount' => '-'.ltrim((string) $item->championship_points, '-'),
                    'source_key' => "division-placement:{$placement->getKey()}:item:{$item->getKey()}:reversal",
                    'source_revision' => $placement->revision,
                    'created_by' => $actor->getKey(),
                    'committed_at' => now(),
                    'reason' => trim($reason),
                ]);
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::DivisionPlacementVoided,
                $placement,
                event: $event,
                reason: trim($reason),
                after: [
                    'state' => DivisionPlacementState::Voided->value,
                    'ledger_effect' => LedgerEntryType::Reversal->value,
                ],
            );

            return $placement->fresh(['items', 'ledgerEntries']);
        });
    }
}
