<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Services\TournamentScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TournamentController extends Controller
{
    public function show(Request $request, Event $event, Division $division): Response
    {
        return $this->workspace($request, $event, $division, null);
    }

    public function showDiscipline(
        Request $request,
        Event $event,
        Division $division,
        Discipline $discipline,
    ): Response {
        return $this->workspace($request, $event, $division, $discipline);
    }

    private function workspace(
        Request $request,
        Event $event,
        Division $division,
        ?Discipline $discipline,
    ): Response {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the Global Admin can open the draw workspace.');
        }

        $division->load([
            'competition',
            'governingRuleVersion.criteria',
            'ruleVersions.criteria',
            'entries.delegation',
            'entries.rosterMembers.participant',
            'entries.eligibilityRecords',
            'entries.disciplineEntries.members.participant',
            'disciplines' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
        ]);
        abort_unless((int) $division->competition?->event_id === (int) $event->getKey(), 404);
        if ($discipline !== null) {
            abort_unless((int) $discipline->competition_division_id === (int) $division->getKey(), 404);
            $discipline->load('division.competition');
        }

        $scope = new TournamentScope($division, $discipline);
        $rule = $division->governingRuleVersion ?? $division->ruleVersions->sortByDesc('version')->first();
        $eligibleIds = $scope->eligibleEntryIds();
        $blockers = $scope->readinessErrors();

        if ($event->isArchived()) {
            $blockers[] = 'This event is archived and its tournament topology is read-only.';
        }

        $scopeEntries = $division->entries
            ->filter(function (Entry $entry) use ($discipline): bool {
                if ($discipline === null) {
                    return true;
                }

                return $entry->disciplineEntries->contains(fn ($item): bool => (int) $item->discipline_id === (int) $discipline->getKey());
            })
            ->values();
        if ($scopeEntries->isEmpty()) {
            $blockers[] = $discipline === null
                ? 'No Entries have been registered for this Division.'
                : 'No department Entries have been assigned to this discipline.';
        } elseif ($eligibleIds->isEmpty()) {
            $blockers[] = $discipline === null
                ? 'Lock at least one eligible Entry before drawing.'
                : 'Lock every assigned discipline Entry and its parent Entry before drawing.';
        }
        $blockers = array_values(array_unique($blockers));

        $tournament = $scope->tournamentQuery()
            ->whereIn('state', ['preview', 'published', 'uncontested'])
            ->with([
                'ruleVersion',
                'drawRecords' => fn ($query) => $query->latest('id'),
                'bracketVersions' => fn ($query) => $query->latest('version'),
            ])
            ->latest('id')
            ->first();
        $bracket = $tournament?->bracketVersions
            ->whereIn('state', [BracketVersionState::Preview->value, BracketVersionState::Published->value])
            ->sortByDesc('version')
            ->first();
        if ($bracket !== null) {
            $bracket->load([
                'nodes' => fn ($query) => $query->orderBy('round_number')->orderBy('sequence'),
                'nodes.slots.entry.delegation',
                'nodes.slots.sourceNode',
                'nodes.contest.officialOutcomes',
            ]);
        }

        $format = $rule?->format()?->value;
        $supported = in_array($format, [
            CompetitionFormat::SingleElimination->value,
            CompetitionFormat::DoubleElimination->value,
            CompetitionFormat::RoundRobin->value,
        ], true);
        $canGenerate = ! $event->isArchived()
            && $supported
            && $tournament === null
            && $blockers === []
            && $eligibleIds->isNotEmpty();
        $canRedraw = ! $event->isArchived()
            && $tournament !== null
            && $tournament->tournamentState()->value === 'preview';
        $canPublish = ! $event->isArchived()
            && $tournament !== null
            && $tournament->tournamentState()->value === 'preview'
            && $bracket?->versionState() === BracketVersionState::Preview;

        return Inertia::render('Admin/Sports/Tournament', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'slug' => $event->slug,
                'archived' => $event->isArchived(),
            ],
            'sport' => [
                'id' => (string) $division->competition->getKey(),
                'name' => $division->competition->name,
                'slug' => $division->competition->slug,
            ],
            'sports' => $this->sportRail($event),
            'division' => [
                'id' => (string) $division->getKey(),
                'name' => $division->name,
                'slug' => $division->slug,
                'format' => $format,
                'participant_mode' => $rule?->participantMode()?->value,
                'scoring_family' => $rule?->scoringFamily()?->value,
                'rule_state' => $rule?->lifecycleState()?->value ?? 'missing',
                'rule_source' => $rule?->source_reference,
                'entry_count' => $division->entries->count(),
                'locked_entry_count' => $division->entries->where('status', EntryStatus::Locked)->count(),
            ],
            'discipline' => $discipline === null ? null : [
                'id' => (string) $discipline->getKey(),
                'name' => $discipline->name,
                'code' => $discipline->code,
                'family' => $discipline->familyType()->value,
                'performance_type' => $discipline->performance_type,
                'metadata' => $discipline->metadata ?? [],
            ],
            'proposal' => [
                'source' => $rule?->source_reference ?? 'Approved-2025-Intramurals-Proposal.pdf',
                'format' => $format,
                'supported_bracket' => $supported,
            ],
            'entries' => $scopeEntries->map(fn (Entry $entry): array => $this->entryPayload($entry, $discipline))->values()->all(),
            'draw' => [
                'order' => $tournament?->drawRecords?->sortByDesc('id')->first()?->draw_order ?? [],
                'source' => $tournament?->drawRecords?->sortByDesc('id')->first()?->source,
                'random_seeded' => $tournament?->drawRecords?->sortByDesc('id')->first()?->random_seed !== null,
            ],
            'tournament' => $tournament === null ? null : [
                'id' => (string) $tournament->getKey(),
                'state' => $tournament->tournamentState()->value,
                'format' => $tournament->formatValue()->value,
                'eligible_entry_count' => (int) $tournament->eligible_entry_count,
                'created_at' => $tournament->created_at?->toIso8601String(),
                'published_at' => $tournament->published_at?->toIso8601String(),
            ],
            'bracket' => $bracket === null ? null : [
                'id' => (string) $bracket->getKey(),
                'version' => (int) $bracket->version,
                'state' => $bracket->versionState()->value,
                'nodes' => $this->nodesPayload($bracket),
            ],
            'blockers' => $blockers,
            'can_generate' => $canGenerate,
            'can_redraw' => $canRedraw,
            'can_publish' => $canPublish,
            'is_archived' => $event->isArchived(),
        ]);
    }

    private function sportRail(Event $event): array
    {
        $event->loadMissing(['competitions.divisions.disciplines', 'competitions.divisions.governingRuleVersion']);

        return $event->competitions->map(fn ($sport): array => [
            'id' => (string) $sport->getKey(),
            'name' => $sport->name,
            'divisions' => $sport->divisions->map(function ($division): array {
                $rule = $division->governingRuleVersion;

                return [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                    'format' => $rule?->format()?->value,
                    'disciplines' => $division->disciplines->where('is_active', true)->map(fn ($discipline): array => [
                        'id' => (string) $discipline->getKey(),
                        'name' => $discipline->name,
                        'family' => $discipline->familyType()->value,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ])->values()->all();
    }

    private function entryPayload(Entry $entry, ?Discipline $discipline): array
    {
        $disciplineEntry = $discipline === null
            ? null
            : $entry->disciplineEntries->firstWhere('discipline_id', $discipline->getKey());

        return [
            'id' => (string) $entry->getKey(),
            'name' => $entry->name,
            'code' => $entry->code,
            'status' => $entry->entryStatus()->value,
            'delegation' => [
                'id' => (string) $entry->delegation?->getKey(),
                'name' => $entry->delegation?->name,
                'abbreviation' => $entry->delegation?->abbreviation,
                'color' => $entry->delegation?->color,
            ],
            'discipline_entry' => $disciplineEntry === null ? null : [
                'id' => (string) $disciplineEntry->getKey(),
                'state' => $disciplineEntry->entryState()->value,
                'members' => $disciplineEntry->members->map(fn ($member): array => [
                    'participant_id' => (string) $member->participant_id,
                    'is_starter' => (bool) $member->is_starter,
                    'is_active' => (bool) $member->is_active,
                    'name' => $member->participant?->display_name,
                ])->values()->all(),
            ],
            'participants' => $entry->rosterMembers->map(fn ($member): array => [
                'id' => (string) $member->participant_id,
                'name' => $member->participant?->display_name,
                'active' => (bool) ($member->is_active && $member->participant?->is_active),
                'eligible' => $entry->eligibilityRecords->contains(fn ($record): bool => (int) $record->participant_id === (int) $member->participant_id && $record->eligibilityStatus()->value === 'eligible'),
            ])->values()->all(),
        ];
    }

    private function nodesPayload($bracket): array
    {
        return $bracket->nodes->map(function ($node): array {
            $metadata = $node->metadata ?? [];
            $key = (string) $node->node_key;
            $side = $metadata['side'] ?? (str_starts_with($key, 'L') ? 'losers' : (str_starts_with($key, 'G') ? 'championship' : 'winners'));

            return [
                'id' => (string) $node->getKey(),
                'key' => $key,
                'type' => $node->nodeType()->value,
                'round' => (int) $node->round_number,
                'sequence' => (int) $node->sequence,
                'state' => $node->nodeState()->value,
                'side' => $side,
                'metadata' => $metadata,
                'contest' => $node->contest === null ? null : [
                    'id' => (string) $node->contest->getKey(),
                    'name' => $node->contest->name,
                    'state' => $node->contest->state?->value ?? (string) $node->contest->state,
                ],
                'slots' => $node->slots->sortBy('slot_number')->map(fn ($slot): array => [
                    'number' => (int) $slot->slot_number,
                    'entry_id' => $slot->entry_id === null ? null : (string) $slot->entry_id,
                    'label' => $slot->entry?->delegation?->abbreviation ?: $slot->entry?->delegation?->name ?: $slot->label ?: 'TBD',
                    'source_node_id' => $slot->source_node_id === null ? null : (string) $slot->source_node_id,
                    'source_result' => $slot->sourceType()?->value,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
