<?php

namespace App\Actions\Brackets;

use App\Enums\AuditAction;
use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\TournamentFormat;
use App\Enums\EntryStatus;
use App\Enums\RuleVersionState;
use App\Enums\TournamentState;
use App\Models\Contest;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TournamentScope;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GenerateRoundRobinSchedule
{
    private ?Discipline $discipline = null;

    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<int>  $drawOrder
     */
    public function handle(User $actor, Division $division, array $drawOrder, string $source = 'manual_draw', ?Discipline $discipline = null): Tournament
    {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can generate a round-robin schedule.');

        return DB::transaction(function () use ($actor, $division, $drawOrder, $source, $event, $discipline): Tournament {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            $division->load('competition.event');
            if ($discipline !== null) {
                $discipline = Discipline::query()
                    ->whereKey($discipline->getKey())
                    ->where('competition_division_id', $division->getKey())
                    ->firstOrFail();
            }
            $this->discipline = $discipline;
            $scope = new TournamentScope($division, $discipline);
            $version = $division->ruleVersions()
                ->where('is_governing', true)
                ->lockForUpdate()
                ->first();

            if ($version === null
                || ! in_array($version->lifecycleState(), [RuleVersionState::ActivatedEditable, RuleVersionState::Frozen], true)
                || $version->format() !== CompetitionFormat::RoundRobin) {
                throw new \DomainException('Only a governing round-robin rule version can generate a schedule.');
            }

            $drawOrder = array_map('intval', array_values($drawOrder));
            $entries = $division->entries()
                ->whereIn('id', $drawOrder)
                ->when($discipline === null,
                    fn ($query) => $query->whereIn('status', [EntryStatus::Active->value, EntryStatus::Locked->value]),
                    fn ($query) => $query->where('status', EntryStatus::Locked->value))
                ->when($discipline !== null, fn ($query) => $query->whereHas('disciplineEntries', fn ($query) => $query
                    ->where('discipline_id', $discipline->getKey())
                    ->where('state', 'locked')))
                ->get()
                ->keyBy(fn ($entry): int => (int) $entry->getKey());

            if ($drawOrder === [] || count($drawOrder) !== count(array_unique($drawOrder)) || count($entries) !== count($drawOrder)) {
                throw new \DomainException('Round-robin schedules require a unique eligible draw order.');
            }

            if ($scope->tournamentQuery()->whereIn('state', [TournamentState::Preview->value, TournamentState::Published->value])->exists()) {
                throw new \DomainException('A preview or published tournament already exists for this Division.');
            }

            $tournament = Tournament::create([
                'competition_division_id' => $division->getKey(),
                'discipline_id' => $discipline?->getKey(),
                'competition_rule_version_id' => $version->getKey(),
                'format' => TournamentFormat::RoundRobin,
                'state' => TournamentState::Preview,
                'eligible_entry_count' => count($drawOrder),
                'created_by' => $actor->getKey(),
                'draw_locked_at' => now(),
            ]);
            $tournament->drawRecords()->create([
                'draw_order' => $drawOrder,
                'source' => $source,
                'confirmed_by' => $actor->getKey(),
                'confirmed_at' => now(),
            ]);
            $bracket = $tournament->bracketVersions()->create([
                'version' => 1,
                'state' => BracketVersionState::Preview,
                'generation_algorithm_version' => 'round-robin-circle-v1',
                'draw_order' => $drawOrder,
                'generation_inputs' => ['entry_count' => count($drawOrder)],
                'created_by' => $actor->getKey(),
            ]);

            $entriesForRounds = $drawOrder;
            if (count($entriesForRounds) % 2 === 1) {
                $entriesForRounds[] = null;
            }

            $roundCount = count($entriesForRounds) - 1;
            for ($round = 1; $round <= $roundCount; $round++) {
                $half = intdiv(count($entriesForRounds), 2);
                for ($index = 0; $index < $half; $index++) {
                    $first = $entriesForRounds[$index];
                    $second = $entriesForRounds[count($entriesForRounds) - 1 - $index];

                    if ($first === null || $second === null) {
                        continue;
                    }

                    $contest = Contest::create([
                        'competition_division_id' => $division->getKey(),
                        'discipline_id' => $discipline?->getKey(),
                        'competition_rule_version_id' => $version->getKey(),
                        'name' => 'Round '.$round.' contest '.($index + 1),
                        'state' => 'scheduled',
                        'revision' => 0,
                    ]);
                    $node = $bracket->nodes()->create([
                        'node_key' => 'R'.$round.'-N'.($index + 1),
                        'node_type' => BracketNodeType::Contest,
                        'round_number' => $round,
                        'sequence' => $index + 1,
                        'state' => 'pending',
                        'contest_id' => $contest->getKey(),
                    ]);
                    $node->slots()->createMany([
                        ['slot_number' => 1, 'entry_id' => $first],
                        ['slot_number' => 2, 'entry_id' => $second],
                    ]);
                }

                $fixed = [$entriesForRounds[0]];
                $rotating = array_slice($entriesForRounds, 1);
                array_unshift($rotating, array_pop($rotating));
                $entriesForRounds = [...$fixed, ...$rotating];
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::BracketGenerated,
                $bracket,
                event: $event,
                after: [
                    'format' => TournamentFormat::RoundRobin->value,
                    'contest_count' => $bracket->nodes()->count(),
                    'rest_slots_are_not_contests' => true,
                ],
            );

            return $tournament->fresh(['bracketVersions.nodes.slots']);
        });
    }
}
