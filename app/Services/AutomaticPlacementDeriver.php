<?php

namespace App\Services;

use App\Actions\Scoring\SubmitDivisionPlacement;
use App\Enums\BracketNodeType;
use App\Enums\CompetitionFormat;
use App\Enums\DivisionPlacementState;
use App\Models\BracketNode;
use App\Models\DivisionPlacement;
use App\Models\OfficialContestOutcome;
use App\Models\Tournament;
use App\Models\User;

final class AutomaticPlacementDeriver
{
    public function derive(User $actor, OfficialContestOutcome $outcome): ?DivisionPlacement
    {
        $outcome->loadMissing('contest.division.governingRuleVersion');
        $division = $outcome->contest?->division;

        if ($division === null || $division->placements()->whereIn('state', [
            DivisionPlacementState::Submitted->value,
            DivisionPlacementState::Approved->value,
        ])->exists()) {
            return null;
        }

        $tournament = $division->tournaments()->where('state', 'published')->with('bracketVersions.nodes.slots')->latest('id')->first();

        if ($tournament === null) {
            return null;
        }

        $bracket = $tournament->bracketVersions->where('state', 'published')->sortByDesc('version')->first();

        if ($bracket === null || $bracket->nodes->contains(
            fn (BracketNode $node): bool => in_array($node->state, ['pending', 'conditional'], true)
        )) {
            return null;
        }

        $rankedIds = match ($tournament->formatValue()) {
            CompetitionFormat::SingleElimination => $this->singleElimination($tournament),
            CompetitionFormat::DoubleElimination => $this->doubleElimination($tournament),
            CompetitionFormat::RoundRobin => $this->roundRobin($tournament),
            default => [],
        };

        if (count($rankedIds) !== $tournament->eligible_entry_count) {
            return null;
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
        $championship = $reset?->state === 'resolved' ? $reset : $grand;
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
