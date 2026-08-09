<?php

namespace App\Services;

use App\Enums\BracketVersionState;
use App\Enums\OfficialOutcomeState;
use App\Models\BracketNode;
use App\Models\OfficialContestOutcome;

final class BracketAdvancer
{
    public function apply(OfficialContestOutcome $outcome): void
    {
        $outcome->loadMissing('contest.entries', 'contest.division.tournaments.bracketVersions');
        $node = BracketNode::query()
            ->with(['bracketVersion', 'advancementRules.targetSlot'])
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
    }
}
