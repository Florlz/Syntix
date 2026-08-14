<?php

namespace App\Services;

use App\Actions\Scoring\SubmitDivisionPlacement;
use App\Enums\BracketNodeType;
use App\Enums\BracketNodeState;
use App\Enums\TournamentFormat;
use App\Enums\DisciplineFamily;
use App\Enums\DisciplinePlacementState;
use App\Enums\DivisionPlacementState;
use App\Models\BracketNode;
use App\Models\DisciplinePlacement;
use App\Models\DivisionPlacement;
use App\Models\DivisionSubPoint;
use App\Models\Entry;
use App\Models\OfficialContestOutcome;
use App\Models\Tournament;
use App\Models\User;

final class AutomaticPlacementDeriver
{
    public function derive(User $actor, OfficialContestOutcome $outcome): ?DivisionPlacement
    {
        $outcome->loadMissing('contest.discipline', 'contest.division.governingRuleVersion', 'contest.division.disciplines');
        $division = $outcome->contest?->division;

        if ($division === null || $division->placements()->whereIn('state', [
            DivisionPlacementState::Submitted->value,
            DivisionPlacementState::Approved->value,
        ])->exists()) {
            return null;
        }

        $discipline = $outcome->contest?->discipline;
        $tournament = $division->tournaments()
            ->where('state', 'published')
            ->when($discipline === null, fn ($query) => $query->whereNull('discipline_id'), fn ($query) => $query->where('discipline_id', $discipline->getKey()))
            ->with('discipline', 'bracketVersions.nodes.slots')
            ->latest('id')
            ->first();

        if ($tournament === null) {
            return null;
        }

        $bracket = $tournament->bracketVersions->where('state', 'published')->sortByDesc('version')->first();

        if ($bracket === null || $bracket->nodes->contains(
            fn (BracketNode $node): bool => $node->nodeState() === BracketNodeState::Pending
        )) {
            return null;
        }

        $rankedIds = match ($tournament->formatValue()) {
            TournamentFormat::SingleElimination => $this->singleElimination($tournament),
            TournamentFormat::DoubleElimination => $this->doubleElimination($tournament),
            TournamentFormat::RoundRobin => $this->roundRobin($tournament),
            default => [],
        };

        if (count($rankedIds) !== $tournament->eligible_entry_count) {
            return null;
        }

        if ($discipline !== null && $discipline->familyType() === DisciplineFamily::Combat) {
            return $this->deriveCombatDiscipline($actor, $tournament, $discipline, $rankedIds);
        }

        $items = collect($rankedIds)->values()->map(fn (int $entryId, int $index): array => [
            'entry_id' => $entryId,
            'rank' => $index + 1,
            'placement_key' => match ($index) {
                0 => 'champion',
                1 => 'first_runner_up',
                2 => 'second_runner_up',
                default => 'participation',
            },
            'participation_eligible' => $index > 2,
        ])->all();

        return (new SubmitDivisionPlacement)->handle($actor, $division, $items, [
            'source' => 'approved_tournament_outcomes',
            'tournament_id' => $tournament->getKey(),
            'automatic' => true,
        ]);
    }

    /** @param list<int> $rankedIds */
    private function deriveCombatDiscipline(User $actor, Tournament $tournament, $discipline, array $rankedIds): ?DivisionPlacement
    {
        if ($discipline->placements()->where('state', DisciplinePlacementState::Approved->value)->exists()) {
            return null;
        }

        $mapping = is_array($discipline->sub_point_configuration) && $discipline->sub_point_configuration !== []
            ? $discipline->sub_point_configuration
            : ['1' => 5, '2' => 3, '3' => 1, 'participation' => 0];
        $division = $tournament->division()->with('entries')->firstOrFail();
        $entries = $division->entries->keyBy(fn (Entry $entry): int => (int) $entry->getKey());

        foreach ($rankedIds as $index => $entryId) {
            $entry = $entries->get($entryId);
            if ($entry === null || $entry->event_delegation_id === null) {
                continue;
            }
            $rank = $index + 1;
            $points = $mapping[(string) $rank] ?? $mapping[$rank] ?? ($mapping['participation'] ?? 0);
            $placement = DisciplinePlacement::create([
                'discipline_id' => $discipline->getKey(),
                'entry_id' => $entry->getKey(),
                'event_delegation_id' => $entry->event_delegation_id,
                'rank' => $rank,
                'sub_points' => $points,
                'state' => DisciplinePlacementState::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ]);
            DivisionSubPoint::create([
                'competition_division_id' => $division->getKey(),
                'discipline_placement_id' => $placement->getKey(),
                'entry_id' => $entry->getKey(),
                'event_delegation_id' => $entry->event_delegation_id,
                'amount' => $points,
                'source_key' => "tournament-discipline:{$tournament->getKey()}:{$discipline->getKey()}:{$rank}",
                'committed_at' => now(),
            ]);
        }

        $required = $division->disciplines->filter(fn ($item): bool => $item->familyType() === DisciplineFamily::Combat && $item->is_active)->count();
        $approved = $division->disciplines->filter(fn ($item): bool => $item->familyType() === DisciplineFamily::Combat && $item->is_active && $item->placements()->where('state', DisciplinePlacementState::Approved->value)->exists())->count();
        if ($required === 0 || $approved < $required) {
            return null;
        }

        $standings = (new DivisionSubPointCalculator)->standings($division);
        if ($standings->count() < 1 || $standings->pluck('sub_point_total')->duplicates()->isNotEmpty()) {
            return null;
        }
        if ($division->placements()->whereIn('state', [DivisionPlacementState::Submitted->value, DivisionPlacementState::Approved->value])->exists()) {
            return null;
        }

        $entryByDelegation = $division->entries->keyBy(fn (Entry $entry): int => (int) $entry->event_delegation_id);
        $items = $standings->values()->map(function ($row, int $index) use ($entryByDelegation): ?array {
            $entry = $entryByDelegation->get((int) $row->event_delegation_id);
            if ($entry === null) {
                return null;
            }

            return [
                'entry_id' => $entry->getKey(),
                'rank' => $index + 1,
                'placement_key' => match ($index) {
                    0 => 'champion', 1 => 'first_runner_up', 2 => 'second_runner_up', default => 'participation'
                },
                'participation_eligible' => $index > 2,
            ];
        })->filter()->values()->all();

        try {
            return (new SubmitDivisionPlacement)->handle($actor, $division, $items, [
                'source' => 'approved_combat_discipline_tournaments',
                'automatic' => true,
            ]);
        } catch (\DomainException) {
            return null;
        }
    }

    /** @return list<int> */
    private function singleElimination(Tournament $tournament): array
    {
        $bracket = $tournament->bracketVersions->where('state', 'published')->first();
        $final = $bracket?->nodes()
            ->where('node_type', BracketNodeType::Contest->value)
            ->whereDoesntHave('advancementRules', fn ($query) => $query->where('outcome', 'winner'))
            ->first();
        $third = $bracket?->nodes->first(fn (BracketNode $node): bool => $node->nodeType() === BracketNodeType::ThirdPlace);
        $ranked = $this->winnerLoser($final);

        if ($third !== null) {
            $thirdIds = $this->winnerLoser($third);
            $ranked = [...$ranked, ...$thirdIds];
        }

        return $this->appendRemaining($tournament, $ranked);
    }

    /** @return list<int> */
    private function doubleElimination(Tournament $tournament): array
    {
        $bracket = $tournament->bracketVersions->where('state', 'published')->first();
        $reset = $bracket?->nodes->first(fn (BracketNode $node): bool => $node->nodeType() === BracketNodeType::ResetFinal);
        $grand = $bracket?->nodes->first(fn (BracketNode $node): bool => ($node->metadata['bracket_side'] ?? null) === 'grand_final');
        $championship = $reset?->nodeState() === BracketNodeState::Resolved ? $reset : $grand;
        $ranked = $this->winnerLoser($championship);
        $losersFinal = $bracket?->nodes
            ->filter(fn (BracketNode $node): bool => ($node->metadata['bracket_side'] ?? null) === 'losers')
            ->sortByDesc('round_number')
            ->first();
        $losersFinalOutcome = $losersFinal?->contest?->currentOfficialOutcome();

        if ($losersFinalOutcome?->payload['loser_entry_id'] ?? null) {
            $ranked[] = (int) $losersFinalOutcome->payload['loser_entry_id'];
        }

        return $this->appendRemaining($tournament, $ranked);
    }

    /** @return list<int> */
    private function roundRobin(Tournament $tournament): array
    {
        $rows = (new TournamentStandingCalculator)->forDivision($tournament->division);
        $tie = $rows->zip($rows->slice(1))->contains(function ($pair): bool {
            if (! isset($pair[1])) {
                return false;
            }

            return collect(['match_points', 'wins', 'differential', 'points_for'])
                ->every(fn (string $key): bool => $pair[0][$key] === $pair[1][$key]);
        });

        return $tie ? [] : $rows->pluck('entry_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return list<int> */
    private function winnerLoser(?BracketNode $node): array
    {
        $outcome = $node?->contest?->currentOfficialOutcome();
        $winner = $outcome?->winner_entry_id;
        $loser = $outcome?->payload['loser_entry_id'] ?? null;

        return array_values(array_filter([(int) $winner, (int) $loser]));
    }

    /** @param list<int> $ranked @return list<int> */
    private function appendRemaining(Tournament $tournament, array $ranked): array
    {
        $drawOrder = $tournament->drawRecords()->latest('id')->first()?->draw_order ?? [];

        foreach ($drawOrder as $entryId) {
            if (! in_array((int) $entryId, $ranked, true)) {
                $ranked[] = (int) $entryId;
            }
        }

        return array_values(array_unique($ranked));
    }
}
