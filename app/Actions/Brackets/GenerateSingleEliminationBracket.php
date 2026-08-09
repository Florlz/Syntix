<?php

namespace App\Actions\Brackets;

use App\Enums\AuditAction;
use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\EntryStatus;
use App\Enums\EventRole;
use App\Enums\RuleVersionState;
use App\Enums\TournamentState;
use App\Models\BracketNode;
use App\Models\BracketVersion;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GenerateSingleEliminationBracket
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<int>  $drawOrder
     */
    public function handle(User $actor, Division $division, array $drawOrder, string $source = 'manual_draw'): Tournament
    {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can generate a bracket.');
        }

        return DB::transaction(function () use ($actor, $division, $drawOrder, $source, $event): Tournament {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            $division->load('competition.event');
            $version = $division->ruleVersions()
                ->where('is_governing', true)
                ->lockForUpdate()
                ->first();

            if ($version === null
                || ! in_array($version->lifecycleState(), [RuleVersionState::ActivatedEditable, RuleVersionState::Frozen], true)
                || $version->format() !== CompetitionFormat::SingleElimination) {
                throw new \DomainException('Only a governing single-elimination rule version can generate a bracket.');
            }

            $existing = $division->tournaments()
                ->whereIn('state', [TournamentState::Published->value, TournamentState::Preview->value])
                ->exists();

            if ($existing) {
                throw new \DomainException('A preview or published tournament already exists for this Division.');
            }

            $drawOrder = array_map('intval', array_values($drawOrder));

            if (count($drawOrder) !== count(array_unique($drawOrder))) {
                throw new \InvalidArgumentException('Draw Order cannot contain duplicate entries.');
            }

            $entries = $division->entries()
                ->whereIn('id', $drawOrder)
                ->whereIn('status', [EntryStatus::Active->value, EntryStatus::Locked->value])
                ->get()
                ->keyBy(fn ($entry): int => (int) $entry->getKey());

            if (count($drawOrder) !== $entries->count()) {
                throw new \DomainException('Every draw entry must be an active or locked eligible entry in the Division.');
            }

            if ($drawOrder === []) {
                throw new \DomainException('A bracket cannot be generated without eligible entries.');
            }

            $tournament = Tournament::create([
                'competition_division_id' => $division->getKey(),
                'competition_rule_version_id' => $version->getKey(),
                'format' => CompetitionFormat::SingleElimination,
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
                ($this->audit ?? new AuditLogger)->record(
                    $actor,
                    AuditAction::BracketGenerated,
                    $tournament,
                    event: $event,
                    after: [
                        'state' => TournamentState::Uncontested->value,
                        'eligible_entry_count' => 1,
                    ],
                );

                return $tournament;
            }

            $bracketSize = self::nextPowerOfTwo(count($drawOrder));
            $openingMatchCount = intdiv($bracketSize, 2);
            $playedOpeningMatchCount = count($drawOrder) - $openingMatchCount;
            $roundCount = (int) log($bracketSize, 2);
            $bracket = $tournament->bracketVersions()->create([
                'version' => 1,
                'state' => BracketVersionState::Preview,
                'generation_algorithm_version' => 'single-elimination-baseline-v1',
                'draw_order' => $drawOrder,
                'generation_inputs' => [
                    'eligible_entry_count' => count($drawOrder),
                    'bracket_size' => $bracketSize,
                    'bye_count' => $bracketSize - count($drawOrder),
                    'opening_match_count' => $openingMatchCount,
                    'played_opening_match_count' => $playedOpeningMatchCount,
                ],
                'created_by' => $actor->getKey(),
            ]);

            $rounds = [];
            $cursor = 0;

            for ($index = 0; $index < $openingMatchCount; $index++) {
                $first = $drawOrder[$cursor++];
                $second = $index < $playedOpeningMatchCount ? $drawOrder[$cursor++] : null;
                $isBye = $second === null;
                $node = $this->createNode(
                    $bracket,
                    $division,
                    $version,
                    round: 1,
                    sequence: $index + 1,
                    type: $isBye ? BracketNodeType::Bye : BracketNodeType::Contest,
                    name: 'Opening contest '.($index + 1),
                    state: $isBye ? 'bye_resolved' : 'pending',
                );
                $node->slots()->create([
                    'slot_number' => 1,
                    'entry_id' => $first,
                ]);
                $node->slots()->create([
                    'slot_number' => 2,
                    'entry_id' => $second,
                    'label' => $isBye ? 'BYE' : null,
                ]);
                $rounds[1][] = $node->fresh('slots');
            }

            for ($round = 2; $round <= $roundCount; $round++) {
                $nodeCount = intdiv($bracketSize, 2 ** $round);
                $previousRound = $rounds[$round - 1];

                for ($index = 0; $index < $nodeCount; $index++) {
                    $node = $this->createNode(
                        $bracket,
                        $division,
                        $version,
                        round: $round,
                        sequence: $index + 1,
                        type: BracketNodeType::Contest,
                        name: $round === $roundCount ? 'Final' : 'Round '.$round.' contest '.($index + 1),
                        state: 'pending',
                    );
                    $firstSource = $previousRound[$index * 2];
                    $secondSource = $previousRound[$index * 2 + 1];
                    $node->slots()->create([
                        'slot_number' => 1,
                        'source_node_id' => $firstSource->getKey(),
                        'source_result' => 'winner',
                        'label' => 'Winner of '.$firstSource->node_key,
                    ]);
                    $node->slots()->create([
                        'slot_number' => 2,
                        'source_node_id' => $secondSource->getKey(),
                        'source_result' => 'winner',
                        'label' => 'Winner of '.$secondSource->node_key,
                    ]);
                    $rounds[$round][] = $node->fresh('slots');
                }
            }

            foreach ($rounds as $round => $nodes) {
                if ($round === $roundCount) {
                    continue;
                }

                $nextRound = $rounds[$round + 1];
                foreach ($nodes as $index => $node) {
                    $target = $nextRound[intdiv($index, 2)];
                    $targetSlot = $target->slots->firstWhere('slot_number', ($index % 2) + 1);
                    $node->advancementRules()->create([
                        'outcome' => 'winner',
                        'target_slot_id' => $targetSlot->getKey(),
                    ]);
                }
            }

            foreach ($bracket->nodes()->where('node_type', BracketNodeType::Bye->value)->with('slots', 'advancementRules')->get() as $bye) {
                $entryId = $bye->slots->firstWhere('entry_id', '!=', null)?->entry_id;

                foreach ($bye->advancementRules->where('outcome', 'winner') as $rule) {
                    $rule->targetSlot()->update(['entry_id' => $entryId]);
                }
            }

            $semifinals = $rounds[$roundCount - 1] ?? [];
            $playedSemifinals = collect($semifinals)->filter(
                fn (BracketNode $node): bool => $node->nodeType() === BracketNodeType::Contest
            )->values();

            if ($playedSemifinals->count() === 2) {
                $thirdPlace = $this->createNode(
                    $bracket,
                    $division,
                    $version,
                    round: $roundCount,
                    sequence: 3,
                    type: BracketNodeType::ThirdPlace,
                    name: 'Third-place playoff',
                    state: 'pending',
                );
                foreach ($playedSemifinals as $index => $semifinal) {
                    $slot = $thirdPlace->slots()->create([
                        'slot_number' => $index + 1,
                        'source_node_id' => $semifinal->getKey(),
                        'source_result' => 'loser',
                        'label' => 'Loser of '.$semifinal->node_key,
                    ]);
                    $semifinal->advancementRules()->create([
                        'outcome' => 'loser',
                        'target_slot_id' => $slot->getKey(),
                    ]);
                }
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::BracketGenerated,
                $bracket,
                event: $event,
                after: [
                    'state' => BracketVersionState::Preview->value,
                    'bracket_size' => $bracketSize,
                    'bye_count' => $bracketSize - count($drawOrder),
                    'third_place_playoff' => $playedSemifinals->count() === 2,
                ],
            );

            return $tournament->fresh(['bracketVersions.nodes.slots']);
        });
    }

    private function createNode(
        BracketVersion $bracket,
        Division $division,
        CompetitionRuleVersion $version,
        int $round,
        int $sequence,
        BracketNodeType $type,
        string $name,
        string $state,
    ): BracketNode {
        $contest = null;

        if (in_array($type, [BracketNodeType::Contest, BracketNodeType::ThirdPlace], true)) {
            $contest = Contest::create([
                'competition_division_id' => $division->getKey(),
                'competition_rule_version_id' => $version->getKey(),
                'name' => $name,
                'state' => 'scheduled',
                'revision' => 0,
            ]);
        }

        return $bracket->nodes()->create([
            'node_key' => 'R'.$round.'-N'.$sequence,
            'node_type' => $type,
            'round_number' => $round,
            'sequence' => $sequence,
            'state' => $state,
            'contest_id' => $contest?->getKey(),
        ]);
    }

    private static function nextPowerOfTwo(int $count): int
    {
        $size = 1;

        while ($size < $count) {
            $size *= 2;
        }

        return $size;
    }
}
