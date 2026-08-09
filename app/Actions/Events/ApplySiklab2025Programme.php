<?php

namespace App\Actions\Events;

use App\Actions\Scoring\ActivateRuleVersion;
use App\Enums\AuditAction;
use App\Enums\CompetitionFormat;
use App\Enums\CriterionNumberMeaning;
use App\Enums\EntryStatus;
use App\Enums\ParticipantMode;
use App\Enums\RoundingMode;
use App\Enums\RuleVersionState;
use App\Enums\ScoringFamily;
use App\Models\CompetitionRuleVersion;
use App\Models\Division;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\OrganizationalUnit;
use App\Models\PlacementPointTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Siklab2025Programme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApplySiklab2025Programme
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, Event $event): Event
    {
        if (! $actor->isGlobalAdmin()) {
            throw new AuthorizationException('Only the active Global Admin can apply the SIKLAB programme.');
        }

        if ($event->isArchived()) {
            throw new \DomainException('The programme cannot be applied to an archived Event.');
        }

        return DB::transaction(function () use ($actor, $event): Event {
            $event = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $delegations = $this->createDelegations($event);

            foreach (Siklab2025Programme::sports() as $sport) {
                $this->createSport($actor, $event, $delegations, $sport);
            }

            foreach (Siklab2025Programme::judgedCompetitions() as $competition) {
                $this->createJudgedCompetition($actor, $event, $delegations, $competition);
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ProgrammeApplied,
                $event,
                $event,
                after: [
                    'programme' => 'SIKLAB 2025 proposal',
                    'delegation_count' => count($delegations),
                    'sport_count' => count(Siklab2025Programme::sports()),
                    'judged_competition_count' => count(Siklab2025Programme::judgedCompetitions()),
                ],
            );

            return $event->fresh(['delegations', 'competitions.divisions.ruleVersions.criteria']);
        });
    }

    /** @return array<int, EventDelegation> */
    private function createDelegations(Event $event): array
    {
        $delegations = [];

        foreach (Siklab2025Programme::teams() as $team) {
            $unit = OrganizationalUnit::query()->updateOrCreate(
                ['slug' => Str::slug($team['name'])],
                [
                    'name' => $team['name'],
                    'abbreviation' => $team['abbreviation'],
                    'default_color' => $team['color'],
                    'is_active' => true,
                ],
            );
            $delegations[] = EventDelegation::query()->updateOrCreate(
                [
                    'event_id' => $event->getKey(),
                    'organizational_unit_id' => $unit->getKey(),
                ],
                [
                    'name' => $team['name'],
                    'abbreviation' => $team['abbreviation'],
                    'color' => $team['color'],
                    'is_active' => true,
                ],
            );
        }

        return $delegations;
    }

    /**
     * @param  array<int, EventDelegation>  $delegations
     * @param  array<string, mixed>  $sport
     */
    private function createSport(User $actor, Event $event, array $delegations, array $sport): void
    {
        $competition = $event->competitions()->firstOrCreate(
            ['slug' => Str::slug((string) $sport['name'])],
            ['name' => $sport['name']],
        );

        foreach ($sport['divisions'] as $divisionName) {
            $division = $competition->divisions()->firstOrCreate(
                ['slug' => Str::slug((string) $divisionName)],
                ['name' => $divisionName],
            );
            $version = $this->createRuleVersion($actor, $division->getKey(), [
                'template' => $sport['template'],
                'scoring_family' => $sport['name'] === 'Athletics' ? ScoringFamily::Aggregate : ScoringFamily::Objective,
                'format' => CompetitionFormat::from((string) $sport['format']),
                'participant_mode' => ParticipantMode::from((string) $sport['participant_mode']),
                'max_roster_size' => $sport['maxRosterSize'] ?? $sport['max_roster_size'] ?? null,
                'source_reference' => $sport['sourceReference'] ?? $sport['source_reference'],
                'source_status' => $sport['sourceStatus'] ?? $sport['source_status'],
                'scoring_configuration' => [
                    'outcome_profile' => $sport['outcomeProfile'] ?? $sport['outcome_profile'],
                    ...($sport['configuration'] ?? []),
                    ...(($sport['blocker'] ?? null) === null ? [] : ['source_blocker' => $sport['blocker']]),
                ],
            ]);

            if ($sport['name'] === 'Athletics') {
                $this->createAthleticsDisciplines($division);
            }

            if ($version->source_status === 'verified'
                && $version->lifecycleState() === RuleVersionState::Draft) {
                (new ActivateRuleVersion)->handle($actor, $version);
            }

            if ($sport['participant_mode'] !== ParticipantMode::Team->value) {
                continue;
            }

            foreach ($delegations as $delegation) {
                $division->entries()->firstOrCreate(
                    ['event_delegation_id' => $delegation->getKey()],
                    [
                        'code' => $delegation->abbreviation,
                        'name' => $delegation->abbreviation.' '.$sport['name'].' '.$divisionName,
                        'entry_mode' => ParticipantMode::Team,
                        'status' => EntryStatus::Active,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<int, EventDelegation>  $delegations
     * @param  array<string, mixed>  $definition
     */
    private function createJudgedCompetition(User $actor, Event $event, array $delegations, array $definition): void
    {
        $competition = $event->competitions()->firstOrCreate(
            ['slug' => Str::slug((string) $definition['name'])],
            ['name' => $definition['name']],
        );
        $division = $competition->divisions()->firstOrCreate(
            ['slug' => 'open'],
            ['name' => 'Open'],
        );
        $weights = collect($definition['criteria'])->pluck(1)->filter(fn ($weight): bool => $weight !== null);
        $version = $this->createRuleVersion($actor, $division->getKey(), [
            'template' => $definition['template'],
            'scoring_family' => ScoringFamily::CriteriaBased,
            'format' => CompetitionFormat::CriteriaBased,
            'participant_mode' => ParticipantMode::from((string) $definition['participantMode']),
            'source_reference' => $definition['sourceReference'],
            'source_status' => $definition['sourceStatus'],
            'verified_scorecard_total' => $weights->sum(),
            'scoring_configuration' => array_filter([
                'outcome_profile' => 'criteria_total',
                'source_blocker' => $definition['blocker'],
            ]),
        ]);

        foreach ($definition['criteria'] as $index => [$name, $weight]) {
            $version->criteria()->firstOrCreate(
                ['display_order' => $index + 1],
                [
                    'name' => $name,
                    'source_label' => $name,
                    'number_meaning' => CriterionNumberMeaning::PercentageWeight,
                    'weight' => $weight,
                    'raw_minimum' => 0,
                    'raw_maximum' => 100,
                    'input_scale' => 2,
                    'is_required' => true,
                    'source_page' => $definition['sourceReference'],
                    'transcription_status' => $definition['sourceStatus'],
                    'approval_reference' => 'Approved-2025-Intramurals-Proposal.pdf',
                ],
            );
        }

        if ($version->source_status === 'verified'
            && $version->lifecycleState() === RuleVersionState::Draft) {
            (new ActivateRuleVersion)->handle($actor, $version);
        }

        foreach ($delegations as $delegation) {
            $division->entries()->firstOrCreate(
                ['event_delegation_id' => $delegation->getKey()],
                [
                    'code' => $delegation->abbreviation,
                    'name' => $delegation->abbreviation.' '.$definition['name'],
                    'entry_mode' => ParticipantMode::from((string) $definition['participantMode']),
                    'status' => EntryStatus::Active,
                ],
            );
        }
    }

    /** @param array<string, mixed> $definition */
    private function createRuleVersion(User $actor, int $divisionId, array $definition): CompetitionRuleVersion
    {
        $template = PlacementPointTemplate::query()
            ->where('code', $definition['template'])
            ->firstOrFail();

        return CompetitionRuleVersion::query()->firstOrCreate(
            [
                'competition_division_id' => $divisionId,
                'version' => 1,
            ],
            [
                'placement_point_template_id' => $template->getKey(),
                'lifecycle_state' => RuleVersionState::Draft,
                'is_governing' => false,
                'scoring_family' => $definition['scoring_family'],
                'format' => $definition['format'],
                'participant_mode' => $definition['participant_mode'],
                'min_roster_size' => 1,
                'max_roster_size' => $definition['max_roster_size'] ?? 1,
                'entries_per_delegation' => 1,
                'participant_competition_limit' => 2,
                'criteria_calculation_mode' => $definition['scoring_family'] === ScoringFamily::CriteriaBased
                    ? CriterionNumberMeaning::PercentageWeight
                    : null,
                'verified_scorecard_total' => $definition['verified_scorecard_total'] ?? null,
                'judge_aggregation_method' => $definition['scoring_family'] === ScoringFamily::CriteriaBased ? 'average' : null,
                'input_scale' => 2,
                'calculation_scale' => 4,
                'display_scale' => 2,
                'comparison_scale' => 4,
                'rounding_mode' => RoundingMode::HalfUp,
                'rounding_stage' => 'final',
                'tie_breaker_configuration' => ['mode' => 'authorized_resolution'],
                'participation_configuration' => ['policy' => 'approved_final_placement'],
                'publication_configuration' => ['live' => true, 'participants' => false],
                'approval_configuration' => ['global_admin_required' => true],
                'scoring_configuration' => $definition['scoring_configuration'],
                'source_reference' => $definition['source_reference'],
                'source_status' => $definition['source_status'],
                'created_by' => $actor->getKey(),
            ],
        );
    }

    private function createAthleticsDisciplines(Division $division): void
    {
        foreach (Siklab2025Programme::athleticsDisciplines() as $definition) {
            $division->disciplines()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'family' => $definition['family'],
                    'performance_type' => $definition['performance_type'],
                    'canonical_unit' => $definition['canonical_unit'],
                    'accepted_input_units' => [$definition['canonical_unit']],
                    'sort_direction' => $definition['sort_direction'],
                    'input_scale' => 3,
                    'storage_scale' => 6,
                    'display_scale' => 3,
                    'tie_breaker_configuration' => ['mode' => 'authorized_resolution'],
                    'sub_point_configuration' => $definition['sub_points'],
                    'metadata' => ['source_reference' => 'Proposal pp. 15–16'],
                    'is_active' => true,
                ],
            );
        }
    }
}
