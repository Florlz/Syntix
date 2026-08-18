<?php

namespace App\Services;

use App\Models\CompetitionRuleVersion;

final class ObjectiveOutcomeValidator
{
    /**
     * Validate declared objective totals against the structured evidence and
     * return the only values that may be persisted as the official result.
     *
     * @param  array<string, mixed>  $payload
     * @return array{home:int|float, away:int|float, result:string, evidence:array<string, mixed>|null}
     */
    public function validate(CompetitionRuleVersion $rule, array $payload): array
    {
        $configuration = $rule->scoring_configuration ?? [];
        $profile = (string) ($configuration['outcome_profile'] ?? 'team_total');
        $evidence = $payload['evidence'] ?? null;
        $derived = $this->deriveFromEvidence($profile, $evidence);

        $home = $derived['home'] ?? $this->score($payload['home'] ?? null, 'home');
        $away = $derived['away'] ?? $this->score($payload['away'] ?? null, 'away');

        if (isset($derived['home']) && array_key_exists('home', $payload) && ! $this->sameNumber($home, $payload['home'])) {
            throw new \DomainException('The declared score does not match the evidence.');
        }
        if (isset($derived['away']) && array_key_exists('away', $payload) && ! $this->sameNumber($away, $payload['away'])) {
            throw new \DomainException('The declared score does not match the evidence.');
        }

        if (in_array($profile, ['best_of_sets', 'team_tie', 'combat_rounds', 'quiz_bowl'], true)
            && (floor((float) $home) !== (float) $home || floor((float) $away) !== (float) $away)) {
            throw new \DomainException('This sport outcome profile requires whole-number official scores.');
        }

        $expectedResult = $this->resultFor($home, $away, $profile);
        $declaredResult = (string) ($payload['result'] ?? '');
        if ($declaredResult !== '' && ! in_array($declaredResult, ['home_win', 'away_win', 'draw'], true)) {
            throw new \DomainException('The objective result is invalid.');
        }
        if ($declaredResult !== '' && $declaredResult !== $expectedResult) {
            throw new \DomainException('The declared result does not match the evidence.');
        }

        return [
            'home' => $home,
            'away' => $away,
            'result' => $expectedResult,
            'evidence' => $evidence,
        ];
    }

    /** @return array{home:int|float, away:int|float}|null */
    private function deriveFromEvidence(string $profile, mixed $evidence): ?array
    {
        if ($evidence === null) {
            return null;
        }
        if (! is_array($evidence) || ($evidence['profile'] ?? null) !== $profile || ! is_array($evidence['data'] ?? null)) {
            throw new \DomainException('The objective evidence does not match this sport outcome profile.');
        }

        $data = $evidence['data'];
        if (in_array($profile, ['best_of_sets', 'combat_rounds'], true)) {
            $homeScores = $this->scoreRows($data['home_scores'] ?? null, $data['away_scores'] ?? null);
            if ($homeScores === null) {
                throw new \DomainException('Objective evidence must include matching home and away score rows.');
            }
            [$homeWins, $awayWins] = [0, 0];
            foreach ($homeScores[0] as $index => $homeScore) {
                $awayScore = $homeScores[1][$index];
                if ($homeScore === $awayScore) {
                    throw new \DomainException('Objective evidence cannot contain tied set or round rows.');
                }
                $homeScore > $awayScore ? $homeWins++ : $awayWins++;
            }

            return ['home' => $homeWins, 'away' => $awayWins];
        }

        if ($profile === 'quiz_bowl') {
            $homeScores = $this->scoreRows($data['home_scores'] ?? null, $data['away_scores'] ?? null);
            if ($homeScores === null) {
                throw new \DomainException('Objective evidence must include matching home and away score rows.');
            }

            return [
                'home' => $this->sum($homeScores[0]),
                'away' => $this->sum($homeScores[1]),
            ];
        }

        if ($profile === 'team_tie') {
            $rubbers = $data['rubbers'] ?? null;
            if (! is_array($rubbers) || count($rubbers) !== 3
                || collect($rubbers)->contains(fn (mixed $winner): bool => ! in_array($winner, ['home', 'away'], true))) {
                throw new \DomainException('Team-tie evidence must identify the winner of all three rubbers.');
            }

            return [
                'home' => collect($rubbers)->filter(fn (string $winner): bool => $winner === 'home')->count(),
                'away' => collect($rubbers)->filter(fn (string $winner): bool => $winner === 'away')->count(),
            ];
        }

        if ($profile === 'chess' && array_key_exists('board_results', $data)) {
            $boardResults = $data['board_results'];
            if (! is_array($boardResults) || $boardResults === []
                || collect($boardResults)->contains(fn (mixed $result): bool => ! in_array($result, ['home_win', 'away_win', 'draw'], true))) {
                throw new \DomainException('Chess evidence must include a valid result for every board.');
            }

            $home = collect($boardResults)->sum(fn (string $result): float => $result === 'home_win' ? 1 : ($result === 'draw' ? 0.5 : 0));
            $away = collect($boardResults)->sum(fn (string $result): float => $result === 'away_win' ? 1 : ($result === 'draw' ? 0.5 : 0));

            return ['home' => $this->number($home), 'away' => $this->number($away)];
        }

        $notes = $data['notes'] ?? '';
        if (! is_string($notes) || mb_strlen($notes) > 5000) {
            throw new \DomainException('Objective evidence notes may not exceed 5,000 characters.');
        }

        return null;
    }

    /** @return array{0: list<int|float>, 1: list<int|float>}|null */
    private function scoreRows(mixed $homeScores, mixed $awayScores): ?array
    {
        if (! is_array($homeScores) || ! is_array($awayScores) || $homeScores === [] || count($homeScores) !== count($awayScores)) {
            return null;
        }

        $home = array_map(fn (mixed $score): int|float => $this->score($score, 'home evidence'), $homeScores);
        $away = array_map(fn (mixed $score): int|float => $this->score($score, 'away evidence'), $awayScores);

        return [$home, $away];
    }

    /** @param list<int|float> $values */
    private function sum(array $values): int|float
    {
        $sum = array_sum($values);

        return $this->number($sum);
    }

    private function resultFor(int|float $home, int|float $away, string $profile): string
    {
        if ($home === $away && $profile !== 'chess') {
            throw new \DomainException('This sport requires a winner; tied final scores cannot be submitted.');
        }

        $expected = $home > $away ? 'home_win' : ($away > $home ? 'away_win' : 'draw');
        return $expected;
    }

    private function score(mixed $value, string $side): int|float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new \DomainException("The {$side} final score must be a non-negative number.");
        }

        return $this->number((float) $value);
    }

    private function number(float|int $value): int|float
    {
        return floor((float) $value) === (float) $value ? (int) $value : $value;
    }

    private function sameNumber(int|float $left, mixed $right): bool
    {
        return is_numeric($right) && abs((float) $left - (float) $right) < 0.000001;
    }
}
