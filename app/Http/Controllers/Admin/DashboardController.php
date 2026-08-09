<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompetitionFormat;
use App\Enums\EventRole;
use App\Enums\RuleVersionState;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Services\TournamentStandingCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TournamentStandingCalculator $standings): Response
    {
        $user = $request->user();
        $globalAdmin = $user->isGlobalAdmin();
        $activeTab = in_array($request->string('tab')->toString(), ['programme', 'access', 'tournaments'], true)
            ? $request->string('tab')->toString()
            : 'programme';
        $events = $this->availableEvents($user, $globalAdmin);
        $requestedEventId = $request->integer('event');
        $event = $events->firstWhere('id', $requestedEventId) ?? $events->first();

        if ($event === null) {
            return Inertia::render('Dashboard', [...$this->emptyPayload($globalAdmin), 'active_tab' => $activeTab]);
        }

        $roles = $globalAdmin
            ? ['global_admin']
            : $user->eventRoles()->active()->where('event_id', $event->getKey())
                ->pluck('role')->map(fn (EventRole|string $role): string => $role instanceof EventRole ? $role->value : (string) $role)
                ->values()->all();

        if (! $globalAdmin) {
            return Inertia::render('Dashboard', [
                ...$this->emptyPayload(false),
                'events' => $events->map($this->eventOption(...))->values(),
                'event' => $this->eventPayload($event, $roles),
                'work_queue' => $this->workQueue($user, $event, $roles),
                'active_tab' => $activeTab,
            ]);
        }

        $event->load([
            'delegations.ledgerEntries',
            'competitions.divisions.governingRuleVersion.criteria',
            'competitions.divisions.entries.delegation',
            'competitions.divisions.tournaments.drawRecords',
            'competitions.divisions.tournaments.bracketVersions',
            'competitions.divisions.contests.resultSubmissions',
            'competitions.divisions.placements',
            'userRoles' => fn ($query) => $query->active()->whereIn('role', [EventRole::Judge->value, EventRole::Tabulator->value])->with('user'),
            'assignments' => fn ($query) => $query->active()->with([
                'user',
                'division.competition',
                'contest.division.competition',
                'entryScorecard.entry',
                'entryScorecard.contest.division.competition',
            ]),
        ]);

        $programme = $event->competitions->flatMap(function ($competition) use ($standings): Collection {
            return $competition->divisions->map(function (Division $division) use ($competition, $standings): array {
                $rule = $division->governingRuleVersion
                    ?? $division->ruleVersions()->latest('version')->first();
                $activeTournament = $division->tournaments
                    ->whereIn('state', ['preview', 'published', 'uncontested'])
                    ->sortByDesc('id')
                    ->first();
                $bracket = $activeTournament?->bracketVersions->sortByDesc('version')->first();
                $draw = $activeTournament?->drawRecords->sortByDesc('id')->first();
                $format = $rule?->format();
                $blockers = $rule?->readinessErrors() ?? ['No rule version is configured.'];
                $drawFormat = in_array($format, [
                    CompetitionFormat::SingleElimination,
                    CompetitionFormat::DoubleElimination,
                    CompetitionFormat::RoundRobin,
                ], true);
                $drawOrder = collect($draw?->draw_order ?? [])->map(function ($entryId) use ($division): array {
                    $entry = $division->entries->firstWhere('id', (int) $entryId);

                    return [
                        'entry_id' => (string) $entryId,
                        'label' => $entry?->delegation?->abbreviation ?? $entry?->name ?? 'Entry '.$entryId,
                    ];
                })->values();

                return [
                    'id' => (string) $division->getKey(),
                    'competition_id' => (string) $competition->getKey(),
                    'competition' => $competition->name,
                    'division' => $division->name,
                    'family' => $rule?->scoringFamily()?->value,
                    'format' => $format?->value,
                    'participant_mode' => $rule?->participantMode()?->value,
                    'outcome_profile' => $rule?->scoring_configuration['outcome_profile'] ?? null,
                    'rule_state' => $rule?->lifecycleState()->value ?? 'missing',
                    'source_reference' => $rule?->source_reference,
                    'source_status' => $rule?->source_status ?? 'missing',
                    'blockers' => $blockers,
                    'criteria' => $rule?->criteria->map(fn ($criterion): array => [
                        'name' => $criterion->name,
                        'weight' => $criterion->weight === null ? null : (float) $criterion->weight,
                    ])->values() ?? [],
                    'entry_count' => $division->entries->count(),
                    'tournament' => $activeTournament === null ? null : [
                        'id' => (string) $activeTournament->getKey(),
                        'state' => $activeTournament->tournamentState()->value,
                        'bracket_id' => $bracket === null ? null : (string) $bracket->getKey(),
                        'bracket_state' => $bracket?->versionState()->value,
                        'source' => $draw?->source,
                        'algorithm_version' => $draw?->algorithm_version,
                        'draw_order' => $drawOrder,
                    ],
                    'can_draw' => $drawFormat
                        && $division->entries->isNotEmpty()
                        && $blockers === []
                        && in_array($rule?->lifecycleState(), [RuleVersionState::ActivatedEditable, RuleVersionState::Frozen], true),
                    'standings' => $activeTournament === null ? [] : $standings->forDivision($division)->all(),
                ];
            });
        })->values();

        $pendingApprovals = $event->competitions->flatMap(function ($competition): Collection {
            return $competition->divisions->flatMap(function ($division) use ($competition): Collection {
                $results = $division->contests->flatMap(fn ($contest) => $contest->resultSubmissions
                    ->where('state', 'submitted')
                    ->map(fn ($submission): array => [
                        'kind' => 'Contest outcome',
                        'id' => (string) $submission->getKey(),
                        'label' => $competition->name.' / '.$division->name.' / '.$contest->name,
                    ]));
                $placements = $division->placements->where('state', 'submitted')->map(fn ($placement): array => [
                    'kind' => 'Final placement',
                    'id' => (string) $placement->getKey(),
                    'label' => $competition->name.' / '.$division->name,
                ]);

                return $results->concat($placements);
            });
        })->values();

        $liveContests = $event->competitions->flatMap(fn ($competition) => $competition->divisions->flatMap(
            fn ($division) => $division->contests->where('state', 'live')->map(fn ($contest): array => [
                'id' => (string) $contest->getKey(),
                'competition' => $competition->name,
                'division' => $division->name,
                'name' => $contest->name,
                'revision' => $contest->revision,
            ])
        ))->values();

        $people = $event->userRoles->map(function ($membership) use ($event): array {
            $role = $membership->role instanceof EventRole ? $membership->role->value : (string) $membership->role;
            $assignments = $event->assignments->where('user_id', $membership->user_id);

            return [
                'id' => (string) $membership->user_id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $role,
                'assignments' => $assignments->map(fn (ScoringAssignment $assignment): array => [
                    'id' => (string) $assignment->getKey(),
                    'scope' => $assignment->scopeType()->value,
                    'label' => $this->assignmentLabel($assignment),
                ])->values(),
            ];
        })->values();

        $drawDivisions = $programme->where('can_draw', true);
        $generatedDraws = $drawDivisions->whereNotNull('tournament')->count();
        $approvedPlacements = $event->competitions->flatMap->divisions->flatMap->placements->where('state', 'approved')->count();

        return Inertia::render('Dashboard', [
            ...$this->emptyPayload(true),
            'events' => $events->map($this->eventOption(...))->values(),
            'event' => $this->eventPayload($event, ['global_admin']),
            'active_tab' => $activeTab,
            'programme' => $programme,
            'teams' => $event->delegations->map(fn ($delegation): array => [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'color' => $delegation->color,
                'championship_total' => (float) $delegation->ledgerEntries->sum('amount'),
            ])->sortByDesc('championship_total')->values(),
            'people' => $people,
            'global_admin' => User::query()->where('is_global_admin', true)->first(['id', 'name', 'email']),
            'pending_approvals' => $pendingApprovals,
            'live_contests' => $liveContests,
            'readiness' => [
                ['key' => 'event', 'label' => 'Event', 'complete' => true, 'detail' => $event->eventState()->value],
                ['key' => 'programme', 'label' => 'Programme', 'complete' => $programme->isNotEmpty(), 'detail' => $programme->count().' divisions'],
                ['key' => 'assignments', 'label' => 'Assignments', 'complete' => $people->isNotEmpty(), 'detail' => $people->count().' scorers'],
                ['key' => 'draws', 'label' => 'Draws', 'complete' => $drawDivisions->isNotEmpty() && $generatedDraws === $drawDivisions->count(), 'detail' => $generatedDraws.'/'.$drawDivisions->count()],
                ['key' => 'live', 'label' => 'Live', 'complete' => $event->eventState()->value === 'live', 'detail' => $liveContests->count().' live'],
                ['key' => 'official', 'label' => 'Official', 'complete' => $approvedPlacements > 0 && $pendingApprovals->isEmpty(), 'detail' => $approvedPlacements.' approved'],
            ],
        ]);
    }

    private function availableEvents(User $user, bool $globalAdmin): Collection
    {
        if ($globalAdmin) {
            return Event::query()->latest('created_at')->get();
        }

        $eventIds = $user->eventRoles()->active()->pluck('event_id');

        return Event::query()->whereIn('id', $eventIds)->latest('created_at')->get();
    }

    private function workQueue(User $user, Event $event, array $roles): Collection
    {
        return $user->scoringAssignments()->active()->where('event_id', $event->getKey())->with([
            'division.competition',
            'contest.division.competition',
            'entryScorecard.entry',
            'entryScorecard.contest.division.competition',
        ])->get()->map(function (ScoringAssignment $assignment) use ($roles): array {
            $url = match ($assignment->scopeType()->value) {
                'contest' => route('tabulator.contests.show', $assignment->contest_id),
                'entry_scorecard' => route('judge.scorecards.show', $assignment->entry_scorecard_id),
                default => null,
            };

            return [
                'id' => (string) $assignment->getKey(),
                'scope' => $assignment->scopeType()->value,
                'label' => $this->assignmentLabel($assignment),
                'roles' => $roles,
                'url' => $url,
            ];
        })->values();
    }

    private function assignmentLabel(ScoringAssignment $assignment): string
    {
        return match ($assignment->scopeType()->value) {
            'competition_division' => ($assignment->division?->competition?->name ?? 'Competition').' — '.($assignment->division?->name ?? 'Division'),
            'contest' => ($assignment->contest?->division?->competition?->name ?? 'Competition').' — '.($assignment->contest?->name ?? 'Contest'),
            'entry_scorecard' => ($assignment->entryScorecard?->contest?->division?->competition?->name ?? 'Competition').' — '.($assignment->entryScorecard?->entry?->name ?? 'Scorecard'),
        };
    }

    private function eventOption(Event $event): array
    {
        return ['id' => (string) $event->getKey(), 'name' => $event->name, 'state' => $event->eventState()->value];
    }

    private function eventPayload(Event $event, array $roles): array
    {
        return [...$this->eventOption($event), 'roles' => $roles];
    }

    private function emptyPayload(bool $globalAdmin): array
    {
        return [
            'events' => [],
            'event' => null,
            'readiness' => [],
            'programme' => [],
            'teams' => [],
            'people' => [],
            'global_admin' => null,
            'pending_approvals' => [],
            'live_contests' => [],
            'work_queue' => [],
            'capabilities' => ['global_admin' => $globalAdmin],
        ];
    }
}
