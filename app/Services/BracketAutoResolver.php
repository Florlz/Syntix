<?php

namespace App\Services;

use App\Enums\BracketNodeType;
use App\Enums\BracketNodeState;
use App\Models\BracketNode;
use App\Models\BracketVersion;
use App\Models\Contest;

final class BracketAutoResolver
{
    public function resolve(BracketVersion $bracket): void
    {
        $changed = true;

        while ($changed) {
            $changed = false;
            $nodes = $bracket->nodes()
                ->with(['slots.sourceNode', 'advancementRules.targetSlot'])
                ->orderBy('id')
                ->get();

            foreach ($nodes as $node) {
                if ($node->nodeState() === BracketNodeState::ByeResolved) {
                    $changed = $this->propagateWinner($node) || $changed;

                    continue;
                }

                if ($node->nodeState() !== BracketNodeState::Pending || $node->nodeType() === BracketNodeType::ResetFinal) {
                    continue;
                }

                $sourceSlots = $node->slots->whereNotNull('source_node_id');

                if ($sourceSlots->isEmpty() || $sourceSlots->contains(
                    fn ($slot): bool => ! in_array($slot->sourceNode?->nodeState(), [BracketNodeState::Resolved, BracketNodeState::ByeResolved], true)
                )) {
                    continue;
                }

                if ($node->slots->whereNotNull('entry_id')->count() > 1) {
                    continue;
                }

                $contestId = $node->contest_id;
                $node->update([
                    'node_type' => BracketNodeType::Bye,
                    'state' => 'bye_resolved',
                    'contest_id' => null,
                ]);

                if ($contestId !== null) {
                    Contest::query()->whereKey($contestId)->delete();
                }

                $changed = $this->propagateWinner($node->fresh(['slots', 'advancementRules.targetSlot'])) || true;
            }
        }

        $bracket->nodes()->with('slots')->get()->each($this->syncContest(...));
    }

    public function syncContest(BracketNode $node): void
    {
        if ($node->contest_id === null) {
            return;
        }

        $node->loadMissing('slots');
        $entryIds = $node->slots->whereNotNull('entry_id')->pluck('entry_id')->map(fn ($id): int => (int) $id);
        $contest = Contest::query()->find($node->contest_id);

        if ($contest === null) {
            return;
        }

        $contest->entries()->whereNotIn('entry_id', $entryIds->all())->delete();

        foreach ($node->slots->whereNotNull('entry_id') as $slot) {
            $contest->entries()->updateOrCreate(
                ['entry_id' => $slot->entry_id],
                ['slot' => $slot->slot_number, 'state' => 'active'],
            );
        }
    }

    private function propagateWinner(BracketNode $node): bool
    {
        $entryId = $node->slots->firstWhere('entry_id', '!=', null)?->entry_id;

        if ($entryId === null) {
            return false;
        }

        $changed = false;

        foreach ($node->advancementRules->where('outcome', 'winner') as $rule) {
            if ((int) $rule->targetSlot->entry_id === (int) $entryId) {
                continue;
            }

            $rule->targetSlot()->update(['entry_id' => $entryId]);
            $changed = true;
        }

        return $changed;
    }
}
