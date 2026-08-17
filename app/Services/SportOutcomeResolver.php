<?php

namespace App\Services;

use App\Enums\OutcomeType;
use App\Models\Contest;

final class SportOutcomeResolver
{
    /**
     * Validate a sport payload and bind the result to the contest's actual
     * entry slots rather than trusting client-submitted entry identifiers.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function resolve(Contest $contest, array $payload): array
    {
        $contest->loadMissing(['entries.entry', 'division.governingRuleVersion']);
        $entries = $contest->entries->sortBy('slot')->values();

        if ($entries->count() !== 2) {
            throw new \DomainException('A playable contest must have exactly two assigned entries.');
        }

        $outcomeType = OutcomeType::tryFrom((string) ($payload['outcome_type'] ?? OutcomeType::Played->value));

        if ($outcomeType === null) {
            throw new \DomainException('The contest outcome type is invalid.');
        }

        $configuration = $contest->division?->governingRuleVersion?->scoring_configuration ?? [];
        $profile = (string) ($configuration['outcome_profile'] ?? 'team_total');
        $homeEntryId = (int) $entries[0]->entry_id;
        $awayEntryId = (int) $entries[1]->entry_id;
        $home = $this->score($payload['home'] ?? null, 'home');
        $away = $this->score($payload['away'] ?? null, 'away');
        $result = (string) ($payload['result'] ?? '');
        $evidence = $payload['evidence'] ?? null;

        if ($evidence !== null) {
            $this->validateEvidence($evidence, $profile);
        }

        if (in_array($profile, ['best_of_sets', 'team_tie', 'combat_rounds', 'quiz_bowl'], true)
            && (floor((float) $home) !== (float) $home || floor((float) $away) !== (float) $away)) {
            throw new \DomainException('This sport outcome profile requires whole-number official scores.');
        }

        if ($profile === 'chess') {
            $result = $result !== '' ? $result : match (true) {
                $home > $away => 'home_win',
                $away > $home => 'away_win',
                default => 'draw',
            };

            if (! in_array($result, ['home_win', 'away_win', 'draw'], true)) {
                throw new \DomainException('Chess results must be a home win, away win, or draw.');
            }
        } else {
            if ($home === $away) {
                throw new \DomainException('This sport requires a winner; tied final scores cannot be submitted.');
            }

            $result = $home > $away ? 'home_win' : 'away_win';
        }

        $winnerId = match ($result) {
            'home_win' => $homeEntryId,
            'away_win' => $awayEntryId,
            default => null,
        };
        $loserId = match ($result) {
            'home_win' => $awayEntryId,
            'away_win' => $homeEntryId,
            default => null,
        };

        return [...$payload, ...[
            'outcome_type' => $outcomeType->value,
            'outcome_profile' => $profile,
            'home' => $home,
            'away' => $away,
            'result' => $result,
            'home_entry_id' => $homeEntryId,
            'away_entry_id' => $awayEntryId,
            'winner_entry_id' => $winnerId,
            'loser_entry_id' => $loserId,
        ]];
    }

    private function score(mixed $value, string $side): int|float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new \DomainException("The {$side} final score must be a non-negative number.");
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private function validateEvidence(mixed $evidence, string $profile): void
    {
        if (! is_array($evidence) || ($evidence['profile'] ?? null) !== $profile || ! is_array($evidence['data'] ?? null)) {
            throw new \DomainException('The objective evidence does not match this sport outcome profile.');
        }

        $data = $evidence['data'];

        if (in_array($profile, ['best_of_sets', 'combat_rounds', 'quiz_bowl'], true)) {
            $homeScores = $data['home_scores'] ?? null;
            $awayScores = $data['away_scores'] ?? null;

            if (! is_array($homeScores) || ! is_array($awayScores) || $homeScores === [] || count($homeScores) !== count($awayScores)) {
                throw new \DomainException('Objective evidence must include matching home and away score rows.');
            }

            foreach ([...$homeScores, ...$awayScores] as $score) {
                if (! is_numeric($score) || (float) $score < 0) {
                    throw new \DomainException('Objective evidence scores must be non-negative numbers.');
                }
            }

            return;
        }

        if ($profile === 'team_tie') {
            $rubbers = $data['rubbers'] ?? null;

            if (! is_array($rubbers) || count($rubbers) !== 3
                || collect($rubbers)->contains(fn (mixed $winner): bool => ! in_array($winner, ['home', 'away'], true))) {
                throw new \DomainException('Team-tie evidence must identify the winner of all three rubbers.');
            }

            return;
        }

        if ($profile === 'chess') {
            $boardResults = $data['board_results'] ?? null;

            if (! is_array($boardResults) || $boardResults === []
                || collect($boardResults)->contains(fn (mixed $result): bool => ! in_array($result, ['home_win', 'away_win', 'draw'], true))) {
                throw new \DomainException('Chess evidence must include a valid result for every board.');
            }

            return;
        }

        $notes = $data['notes'] ?? '';

        if (! is_string($notes) || mb_strlen($notes) > 5000) {
            throw new \DomainException('Objective evidence notes may not exceed 5,000 characters.');
        }
    }
}
