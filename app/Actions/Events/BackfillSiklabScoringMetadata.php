<?php

namespace App\Actions\Events;

use App\Enums\AuditAction;
use App\Models\CompetitionRuleVersion;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Siklab2025Programme;

final class BackfillSiklabScoringMetadata
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * Add only non-calculation programme metadata to existing SIKLAB rules.
     *
     * @return array{updated: int, unchanged: int, skipped: int, changes: list<array<string, mixed>>}
     */
    public function handle(Event $event, bool $dryRun = false, ?User $actor = null): array
    {
        $definitions = collect(Siklab2025Programme::judgedCompetitions())->keyBy('name');
        $report = ['updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'changes' => []];
        $event->load('competitions.divisions.ruleVersions');

        foreach ($event->competitions as $competition) {
            $definition = $definitions->get($competition->name);
            if ($definition === null) continue;

            foreach ($competition->divisions as $division) {
                /** @var CompetitionRuleVersion|null $rule */
                $rule = $division->ruleVersions->sortByDesc('version')->first();
                if ($rule === null) {
                    $report['skipped']++;
                    continue;
                }

                $changes = $this->safeChanges($rule, $definition);
                if ($changes === []) {
                    $report['unchanged']++;
                    continue;
                }

                $report['changes'][] = [
                    'competition' => $competition->name,
                    'division' => $division->name,
                    'rule_version_id' => (string) $rule->getKey(),
                    'fields' => array_keys($changes),
                    'protected_calculation' => $rule->isFrozenOrHistorical() || $division->hasScoringStarted(),
                ];
                $report['updated']++;

                if ($dryRun) continue;

                $before = [
                    'source_reference' => $rule->source_reference,
                    'scoring_configuration' => $rule->scoring_configuration ?? [],
                ];
                $protectedCalculation = $rule->isFrozenOrHistorical() || $division->hasScoringStarted();
                $target = $protectedCalculation ? $this->createMetadataVersion($rule, $changes) : $rule;
                if (! $protectedCalculation) {
                    $rule->forceFill($changes)->save();
                }
                ($this->audit ?? new AuditLogger)->record(
                    $actor,
                    AuditAction::ScoringMetadataBackfilled,
                    $target,
                    event: $event,
                    before: $before,
                    after: [
                        'rule_version_id' => (string) $target->getKey(),
                        'source_reference' => $target->source_reference,
                        'scoring_configuration' => $target->scoring_configuration ?? [],
                    ],
                    reason: 'Safe SIKLAB programme metadata backfill; calculation fields were not changed.',
                    context: ['protected_calculation' => $protectedCalculation],
                );
            }
        }

        return $report;
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function safeChanges(CompetitionRuleVersion $rule, array $definition): array
    {
        $configuration = $rule->scoring_configuration ?? [];
        $metadata = [
            'reliability_label' => $definition['reliability_label'] ?? null,
            'source_pages' => $definition['source_pages'] ?? [],
            'event_controls' => $definition['event_controls'] ?? [],
            'venue_candidates' => $definition['venue_candidates'] ?? [],
            'programme_day_hint' => $definition['programme_day_hint'] ?? null,
            'source_blocker' => $definition['source_blocker'] ?? null,
        ];
        $changes = [];

        foreach ($metadata as $key => $value) {
            if ($this->emptyMetadata($configuration[$key] ?? null) && ! $this->emptyMetadata($value)) {
                $configuration[$key] = $value;
            }
        }

        $correctedPages = $this->correctedSourcePages((string) ($definition['name'] ?? ''), $configuration['source_pages'] ?? null, $metadata['source_pages']);
        if ($correctedPages !== null) $configuration['source_pages'] = $correctedPages;

        $sourceReference = (string) ($definition['source_reference'] ?? $definition['sourceReference'] ?? '');
        if ($sourceReference !== '' && ($rule->source_reference === null || trim((string) $rule->source_reference) === '' || $this->legacyReference((string) ($definition['name'] ?? ''), (string) $rule->source_reference))) {
            $changes['source_reference'] = $sourceReference;
        }
        if ($configuration !== ($rule->scoring_configuration ?? [])) {
            $changes['scoring_configuration'] = $configuration;
        }

        return $changes;
    }

    /** @param array<string, mixed> $changes */
    private function createMetadataVersion(CompetitionRuleVersion $rule, array $changes): CompetitionRuleVersion
    {
        $replacement = $rule->replicate();
        $replacement->forceFill([
            'version' => ((int) $rule->version) + 1,
            'lifecycle_state' => 'draft',
            'is_governing' => false,
            'supersedes_id' => $rule->getKey(),
            'activated_by' => null,
            'activated_at' => null,
            'frozen_at' => null,
            ...$changes,
        ])->save();

        foreach ($rule->criteria()->get() as $criterion) {
            $replacementCriterion = $criterion->replicate();
            $replacementCriterion->competition_rule_version_id = $replacement->getKey();
            $replacementCriterion->save();
        }

        return $replacement;
    }

    private function emptyMetadata(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function legacyReference(string $name, string $reference): bool
    {
        $legacy = [
            'Essay Writing' => ['Proposal p. 18', 'Proposal p. 19'],
            'Pagsulat ng Sanaysay' => ['Proposal p. 18', 'Proposal p. 19'],
            'Radio Drama' => ['Proposal p. 20', 'Proposal p. 21'],
            'Pop Solo' => ['Proposal p. 20'],
            'Kundiman' => ['Proposal p. 20'],
            'Instrumental Solo — Bandurria' => ['Proposal p. 21'],
            'Instrumental Solo — Piano' => ['Proposal p. 21'],
            'Instrumental Solo — Classical Guitar' => ['Proposal p. 21'],
            'Contemporary Dance' => ['Proposal p. 22'],
            'Dance Sports' => ['Proposal p. 22', 'Proposal pp. 23–24'],
            'Cheer Dance' => ['Proposal p. 24', 'Proposal p. 28'],
        ];

        return in_array($reference, $legacy[$name] ?? [], true);
    }

    /** @param list<int> $expected @return list<int>|null */
    private function correctedSourcePages(string $name, mixed $current, array $expected): ?array
    {
        if ($this->emptyMetadata($current) || ! is_array($current)) return null;
        $legacy = [
            'Essay Writing' => [[18], [19]],
            'Pagsulat ng Sanaysay' => [[18], [19]],
            'Radio Drama' => [[21]],
            'Pop Solo' => [[20]],
            'Kundiman' => [[20]],
            'Instrumental Solo — Bandurria' => [[21]],
            'Instrumental Solo — Piano' => [[21]],
            'Instrumental Solo — Classical Guitar' => [[21]],
            'Contemporary Dance' => [[22], [23, 24]],
            'Dance Sports' => [[22], [23, 24]],
            'Cheer Dance' => [[24], [28]],
        ];

        foreach ($legacy[$name] ?? [] as $old) {
            if (array_values($current) === $old) return $expected;
        }

        return null;
    }
}
