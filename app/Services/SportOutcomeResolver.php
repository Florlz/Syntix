<?php

namespace App\Services;

use App\Enums\OutcomeType;
use App\Models\Contest;

final class SportOutcomeResolver
{
    public function __construct(private readonly ?ObjectiveOutcomeValidator $validator = null) {}

    /**
     * Bind normalized objective evidence to the contest's actual entry slots.
     * Browser-supplied entry IDs and totals never become authoritative here.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function resolve(Contest $contest, array $payload): array
    {
        $contest->loadMissing(['entries.entry', 'ruleVersion']);
        $entries = $contest->entries->sortBy('slot')->values();

        if ($entries->count() !== 2) {
            throw new \DomainException('A playable contest must have exactly two assigned entries.');
        }

        $outcomeType = OutcomeType::tryFrom((string) ($payload['outcome_type'] ?? OutcomeType::Played->value));
        if ($outcomeType === null) {
            throw new \DomainException('The contest outcome type is invalid.');
        }
        if ($contest->ruleVersion === null) {
            throw new \DomainException('The contest scoring rule is missing.');
        }

        $normalized = ($this->validator ?? new ObjectiveOutcomeValidator)->validate($contest->ruleVersion, $payload);
        $homeEntryId = (int) $entries[0]->entry_id;
        $awayEntryId = (int) $entries[1]->entry_id;
        $winnerId = match ($normalized['result']) {
            'home_win' => $homeEntryId,
            'away_win' => $awayEntryId,
            default => null,
        };
        $loserId = match ($normalized['result']) {
            'home_win' => $awayEntryId,
            'away_win' => $homeEntryId,
            default => null,
        };

        return [
            ...$payload,
            'outcome_type' => $outcomeType->value,
            'outcome_profile' => (string) (($contest->ruleVersion->scoring_configuration ?? [])['outcome_profile'] ?? 'team_total'),
            'home' => $normalized['home'],
            'away' => $normalized['away'],
            'result' => $normalized['result'],
            'evidence' => $normalized['evidence'],
            'home_entry_id' => $homeEntryId,
            'away_entry_id' => $awayEntryId,
            'winner_entry_id' => $winnerId,
            'loser_entry_id' => $loserId,
        ];
    }
}
