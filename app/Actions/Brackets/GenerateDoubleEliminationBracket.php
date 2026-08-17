<?php

namespace App\Actions\Brackets;

use App\Enums\AuditAction;
use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\TournamentFormat;
use App\Enums\RuleVersionState;
use App\Enums\TournamentState;
use App\Models\BracketNode;
use App\Models\BracketVersion;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BracketAutoResolver;
use App\Services\TournamentScope;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GenerateDoubleEliminationBracket
{
    private ?Discipline $discipline = null;

    public function __construct(
        private readonly ?AuditLogger $audit = null,
        private readonly ?BracketAutoResolver $autoResolver = null,
    ) {}

    /**
     * Generate the standard two-loss route used by the proposal's racket
     * sports. The signed implementation supports draw sizes two through eight.
     *
     * @param  list<int>  $drawOrder
     */
    public function handle(
        User $actor,
        Division $division,
        array $drawOrder,
        string $source = 'manual_draw',
        ?Discipline $discipline = null,
    ): Tournament {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can generate a double-elimination bracket.');

        return DB::transaction(function () use ($actor, $division, $drawOrder, $source, $event, $discipline): Tournament {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            if ($discipline !== null) {
                $discipline = Discipline::query()
                    ->whereKey($discipline->getKey())
                    ->where('competition_division_id', $division->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $this->discipline = $discipline;
            $scope = new TournamentScope($division, $discipline);
            $version = $division->ruleVersions()->where('is_governing', true)->lockForUpdate()->first();

            if ($version === null
                || ! in_array($version->lifecycleState(), [RuleVersionState::ActivatedEditable, RuleVersionState::Frozen], true)
                || $version->format() !== CompetitionFormat::DoubleElimination) {
                throw new \DomainException('Only a governing double-elimination rule version can generate this bracket.');
            }

            if ($scope->tournamentQuery()->whereIn('state', [TournamentState::Preview->value, TournamentState::Published->value])->exists()) {
                throw new \DomainException('A preview or published tournament already exists for this Division.');
            }

            $drawOrder = array_map('intval', array_values($drawOrder));
            $scope->assertDrawOrder($drawOrder);

            if (count($drawOrder) > 8) {
                throw new \DomainException('The signed double-elimination route currently supports at most eight entries.');
            }

            $tournament = Tournament::create([
                'competition_division_id' => $division->getKey(),
                'discipline_id' => $discipline?->getKey(),
                'competition_rule_version_id' => $version->getKey(),
                'format' => TournamentFormat::DoubleElimination,
                'state' => count($drawOrder) === 1 ? TournamentState::Uncontested : TournamentState::Preview,
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

            if (count($drawOrder) === 1) {
                return $tournament;
            }

            $size = count($drawOrder) <= 2 ? 2 : (count($drawOrder) <= 4 ? 4 : 8);
            $bracket = $tournament->bracketVersions()->create([
                'version' => 1,
                'state' => BracketVersionState::Preview,
                'generation_algorithm_version' => 'double-elimination-2-4-8-v1',
                'draw_order' => $drawOrder,
                'generation_inputs' => [
                    'eligible_entry_count' => count($drawOrder),
                    'bracket_size' => $size,
                    'bye_count' => $size - count($drawOrder),
                    'reset_final' => true,
                ],
                'created_by' => $actor->getKey(),
            ]);

            $opening = $this->openingRound($bracket, $division, $version, $drawOrder, $size);

            if ($size === 2) {
                $winnerFinal = $opening[0];
                $loserFinal = $opening[0];
            } elseif ($size === 4) {
                $winnerFinal = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'WB-R2-N1',
                    'Winners bracket final',
                    2,
                    [[$opening[0], 'winner'], [$opening[1], 'winner']],
                    'winners',
                );
                $losersOpening = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'LB-R1-N1',
                    'Losers bracket opening contest',
                    2,
                    [[$opening[0], 'loser'], [$opening[1], 'loser']],
                    'losers',
                );
                $loserFinal = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'LB-R2-N1',
                    'Losers bracket final',
                    3,
                    [[$losersOpening, 'winner'], [$winnerFinal, 'loser']],
                    'losers',
                );
            } else {
                $winnerSemifinals = [
                    $this->sourceNode($bracket, $division, $version, 'WB-R2-N1', 'Winners semifinal 1', 2, [[$opening[0], 'winner'], [$opening[1], 'winner']], 'winners'),
                    $this->sourceNode($bracket, $division, $version, 'WB-R2-N2', 'Winners semifinal 2', 2, [[$opening[2], 'winner'], [$opening[3], 'winner']], 'winners'),
                ];
                $winnerFinal = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'WB-R3-N1',
                    'Winners bracket final',
                    3,
                    [[$winnerSemifinals[0], 'winner'], [$winnerSemifinals[1], 'winner']],
                    'winners',
                );
                $losersOpening = [
                    $this->sourceNode($bracket, $division, $version, 'LB-R1-N1', 'Losers opening contest 1', 2, [[$opening[0], 'loser'], [$opening[1], 'loser']], 'losers'),
                    $this->sourceNode($bracket, $division, $version, 'LB-R1-N2', 'Losers opening contest 2', 2, [[$opening[2], 'loser'], [$opening[3], 'loser']], 'losers'),
                ];
                $losersSecond = [
                    $this->sourceNode($bracket, $division, $version, 'LB-R2-N1', 'Losers contest 3', 3, [[$losersOpening[0], 'winner'], [$winnerSemifinals[1], 'loser']], 'losers'),
                    $this->sourceNode($bracket, $division, $version, 'LB-R2-N2', 'Losers contest 4', 3, [[$losersOpening[1], 'winner'], [$winnerSemifinals[0], 'loser']], 'losers'),
                ];
                $losersSemifinal = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'LB-R3-N1',
                    'Losers bracket semifinal',
                    4,
                    [[$losersSecond[0], 'winner'], [$losersSecond[1], 'winner']],
                    'losers',
                );
                $loserFinal = $this->sourceNode(
                    $bracket,
                    $division,
                    $version,
                    'LB-R4-N1',
                    'Losers bracket final',
                    5,
                    [[$losersSemifinal, 'winner'], [$winnerFinal, 'loser']],
                    'losers',
                );
            }

            $grandFinal = $this->sourceNode(
                $bracket,
                $division,
                $version,
                'GF-N1',
                'Grand final',
                $size === 8 ? 6 : ($size === 4 ? 4 : 2),
                [[$winnerFinal, 'winner'], [$loserFinal, $size === 2 ? 'loser' : 'winner']],
                'grand_final',
            );
            $grandFinal->update(['metadata' => ['bracket_side' => 'grand_final', 'conditional_reset' => true]]);

            $reset = $this->node(
                $bracket,
                $division,
                $version,
                'GF-RESET',
                'Grand final reset (if required)',
                ($grandFinal->round_number ?? 0) + 1,
                BracketNodeType::ResetFinal,
                'pending',
                'reset_final',
            );
            $reset->slots()->createMany([
                ['slot_number' => 1, 'source_node_id' => $grandFinal->getKey(), 'source_result' => 'reset_participant'],
                ['slot_number' => 2, 'source_node_id' => $grandFinal->getKey(), 'source_result' => 'reset_participant'],
            ]);

            ($this->autoResolver ?? new BracketAutoResolver)->resolve($bracket);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::BracketGenerated,
                $bracket,
                event: $event,
                after: [
                    'format' => TournamentFormat::DoubleElimination->value,
                    'bracket_size' => $size,
                    'bye_count' => $size - count($drawOrder),
                    'reset_final' => true,
                ],
            );

            return $tournament->fresh(['bracketVersions.nodes.slots']);
        });
    }

    /** @return list<BracketNode> */
    private function openingRound(
        BracketVersion $bracket,
        Division $division,
        CompetitionRuleVersion $version,
        array $drawOrder,
        int $size,
    ): array {
        $matchCount = intdiv($size, 2);
        $playedCount = count($drawOrder) - $matchCount;
        $cursor = 0;
        $nodes = [];

        for ($index = 0; $index < $matchCount; $index++) {
            $first = $drawOrder[$cursor++];
            $second = $index < $playedCount ? $drawOrder[$cursor++] : null;
            $isBye = $second === null;
            $node = $this->node(
                $bracket,
                $division,
                $version,
                'WB-R1-N'.($index + 1),
                'Winners opening contest '.($index + 1),
                1,
                $isBye ? BracketNodeType::Bye : BracketNodeType::Contest,
                $isBye ? 'bye_resolved' : 'pending',
                'winners',
            );
            $node->slots()->createMany([
                ['slot_number' => 1, 'entry_id' => $first],
                ['slot_number' => 2, 'entry_id' => $second, 'label' => $isBye ? 'BYE' : null],
            ]);
            $nodes[] = $node->fresh('slots');
        }

        return $nodes;
    }

    /** @param list<array{0: BracketNode, 1: string}> $sources */
    private function sourceNode(
        BracketVersion $bracket,
        Division $division,
        CompetitionRuleVersion $version,
        string $key,
        string $name,
        int $round,
        array $sources,
        string $side,
    ): BracketNode {
        $target = $this->node($bracket, $division, $version, $key, $name, $round, BracketNodeType::Contest, 'pending', $side);

        foreach ($sources as $index => [$source, $outcome]) {
            $slot = $target->slots()->create([
                'slot_number' => $index + 1,
                'source_node_id' => $source->getKey(),
                'source_result' => $outcome,
                'label' => ucfirst($outcome).' of '.$source->node_key,
            ]);
            $source->advancementRules()->create([
                'outcome' => $outcome,
                'target_slot_id' => $slot->getKey(),
            ]);
        }

        return $target->fresh('slots');
    }

    private function node(
        BracketVersion $bracket,
        Division $division,
        CompetitionRuleVersion $version,
        string $key,
        string $name,
        int $round,
        BracketNodeType $type,
        string $state,
        string $side,
    ): BracketNode {
        $contest = $type === BracketNodeType::Bye ? null : Contest::create([
            'competition_division_id' => $division->getKey(),
            'discipline_id' => $this->discipline?->getKey(),
            'competition_rule_version_id' => $version->getKey(),
            'name' => $name,
            'state' => 'scheduled',
            'revision' => 0,
        ]);

        preg_match('/N(\d+)$/', $key, $matches);
        $sequence = isset($matches[1]) ? (int) $matches[1] : 1;

        return $bracket->nodes()->create([
            'node_key' => $key,
            'node_type' => $type,
            'round_number' => $round,
            'sequence' => $sequence,
            'state' => $state,
            'contest_id' => $contest?->getKey(),
            'metadata' => ['bracket_side' => $side],
        ]);
    }
}
