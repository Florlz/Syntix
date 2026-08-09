<?php

namespace App\Services;

use App\Enums\OfficialOutcomeState;
use App\Models\Division;
use App\Models\OfficialContestOutcome;
use Illuminate\Support\Collection;

final class TournamentStandingCalculator
{
    /** @return Collection<int, array<string, mixed>> */
    public function forDivision(Division $division): Collection
    {
        $division->loadMissing(['entries.delegation', 'governingRuleVersion']);
        $configuration = $division->governingRuleVersion?->scoring_configuration ?? [];
        $winPoints = (float) ($configuration['win_points'] ?? 1);
        $drawPoints = (float) ($configuration['draw_points'] ?? 0.5);
        $lossPoints = (float) ($configuration['loss_points'] ?? 0);
        $rows = $division->entries->mapWithKeys(fn ($entry): array => [
            (int) $entry->getKey() => [
                'entry_id' => (int) $entry->getKey(),
                'entry_name' => $entry->name,
                'delegation' => $entry->delegation?->abbreviation,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'match_points' => 0.0,
                'points_for' => 0.0,
                'points_against' => 0.0,
                'differential' => 0.0,
            ],
        ])->all();

        $outcomes = OfficialContestOutcome::query()
            ->where('state', OfficialOutcomeState::Approved->value)
            ->whereHas('contest', fn ($query) => $query->where('competition_division_id', $division->getKey()))
            ->get();

        foreach ($outcomes as $outcome) {
            $payload = $outcome->payload ?? [];
            $homeId = (int) ($payload['home_entry_id'] ?? 0);
            $awayId = (int) ($payload['away_entry_id'] ?? 0);

            if (! isset($rows[$homeId], $rows[$awayId])) {
                continue;
            }

            $homeScore = (float) ($payload['home'] ?? 0);
            $awayScore = (float) ($payload['away'] ?? 0);
            $rows[$homeId]['played']++;
            $rows[$awayId]['played']++;
            $rows[$homeId]['points_for'] += $homeScore;
            $rows[$homeId]['points_against'] += $awayScore;
            $rows[$awayId]['points_for'] += $awayScore;
            $rows[$awayId]['points_against'] += $homeScore;

            if (($payload['result'] ?? null) === 'draw') {
                $rows[$homeId]['draws']++;
                $rows[$awayId]['draws']++;
                $rows[$homeId]['match_points'] += $drawPoints;
                $rows[$awayId]['match_points'] += $drawPoints;
            } else {
                $winnerId = (int) ($outcome->winner_entry_id ?? 0);
                $loserId = $winnerId === $homeId ? $awayId : $homeId;

                if (! isset($rows[$winnerId], $rows[$loserId])) {
                    continue;
                }

                $rows[$winnerId]['wins']++;
                $rows[$loserId]['losses']++;
                $rows[$winnerId]['match_points'] += $winPoints;
                $rows[$loserId]['match_points'] += $lossPoints;
            }
        }

        foreach ($rows as &$row) {
            $row['differential'] = $row['points_for'] - $row['points_against'];
        }
        unset($row);

        return collect($rows)->sort(function (array $left, array $right): int {
            return $right['match_points'] <=> $left['match_points']
                ?: $right['wins'] <=> $left['wins']
                ?: $right['differential'] <=> $left['differential']
                ?: $right['points_for'] <=> $left['points_for']
                ?: $left['entry_id'] <=> $right['entry_id'];
        })->values()->map(function (array $row, int $index): array {
            $row['rank'] = $index + 1;

            return $row;
        });
    }
}
