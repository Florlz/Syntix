<?php

namespace App\Services;

use App\Enums\BracketVersionState;
use App\Enums\ContestState;
use App\Enums\OfficialOutcomeState;
use App\Models\BracketNode;
use App\Models\OfficialContestOutcome;

final class BracketAdvancer
{
    public function apply(OfficialContestOutcome $outcome): void
    {
        $outcome->loadMissing('contest.entries', 'contest.division.tournaments.bracketVersions');
        $node = BracketNode::query()
            ->with(['bracketVersion', 'slots', 'advancementRules.targetSlot'])
            ->where('contest_id', $outcome->contest_id)
            ->whereHas('bracketVersion', fn ($query) => $query->where('state', BracketVersionState::Published->value))
            ->lockForUpdate()
            ->first();

        if ($node === null || $outcome->outcomeState() !== OfficialOutcomeState::Approved) {
            return;
        }

        $winnerId = $outcome->winner_entry_id;
        $entryIds = $outcome->contest->entries->pluck('entry_id')->map(fn ($id): int => (int) $id);
        $loserId = $outcome->payload['loser_entry_id'] ?? $entryIds->first(fn (int $id): bool => $id !== (int) $winnerId);

        foreach ($node->advancementRules as $rule) {
            $entryId = match ($rule->outcome) {
                'winner' => $winnerId,
                'loser' => $loserId,
                default => null,
            };

            if ($entryId === null) {
                continue;
            }

            $rule->targetSlot()->update([
                'entry_id' => $entryId,
            ]);
        }

        $node->update(['state' => 'resolved']);
        $this->resolveConditionalReset($node, (int) $winnerId);
        (new BracketAutoResolver)->resolve($node->bracketVersion);
    }

    private function resolveConditionalReset(BracketNode $node, int $winnerId): void
    {
        if (($node->metadata['bracket_side'] ?? null) !== 'grand_final') {
            return;
        }

        $reset = $node->bracketVersion->nodes()
            ->with('slots')
            ->where('node_type', 'reset_final')
            ->first();

        if ($reset === null) {
            return;
        }

        $losersBracketEntryId = (int) ($node->slots->firstWhere('slot_number', 2)?->entry_id ?? 0);

        if ($winnerId === $losersBracketEntryId) {
            foreach ($node->slots->whereNotNull('entry_id') as $slot) {
                $reset->slots()->where('slot_number', $slot->slot_number)->update(['entry_id' => $slot->entry_id]);
            }
            $reset->update(['state' => 'pending']);

            return;
        }

        $reset->update(['state' => 'skipped']);
        $reset->contest?->update([
            'state' => ContestState::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'The winners-bracket finalist won the grand final; no reset was required.',
        ]);
    }
}
