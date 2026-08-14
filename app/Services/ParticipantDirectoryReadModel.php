<?php

namespace App\Services;

use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Models\CoachAssignment;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use Illuminate\Support\Collection;

final class ParticipantDirectoryReadModel
{
    public const PREVIEW_LIMIT = 25;

    /**
     * Build the event-wide explorer without hydrating participant rows. The
     * summary contains only departments, sport/division groupings, roster
     * metadata, and filtered counts; person records arrive through preview().
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summaryForEvent(Event $event, ?string $query = null, array $filters = []): array
    {
        $departments = $event->delegations()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $competitions = $event->competitions()
            ->where('is_active', true)
            ->with(['divisions' => fn ($builder) => $builder->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();
        $departmentIds = $departments->pluck('id');
        $divisionIds = $competitions->flatMap->divisions->pluck('id');

        $entries = Entry::query()
            ->whereIn('event_delegation_id', $departmentIds)
            ->whereIn('competition_division_id', $divisionIds)
            ->with(['division.competition'])
            ->withCount([
                'rosterMembers as active_player_count' => function ($builder) use ($query, $filters): void {
                    $builder->where('is_active', true)
                        ->whereIn('role', $this->playerRoleValues())
                        ->whereHas('participant', function ($people) use ($query, $filters): void {
                            $this->applyParticipantCriteria($people, $query, $filters, true);
                        });
                },
                'rosterMembers as active_member_count' => fn ($builder) => $builder->where('is_active', true),
            ])
            ->orderByDesc('id')
            ->get();

        $entriesByDepartment = $entries->groupBy(fn (Entry $entry): string => (string) $entry->event_delegation_id);
        $assignments = CoachAssignment::query()
            ->where('event_id', $event->getKey())
            ->where('is_active', true)
            ->with('participant:id,event_delegation_id,display_name,is_active')
            ->get(['id', 'event_id', 'event_delegation_id', 'participant_id', 'coach_type', 'title', 'scope_type', 'scope_key', 'is_active', 'notes']);
        $assignments = $assignments
            ->filter(fn (CoachAssignment $assignment): bool => $this->assignmentMatchesCriteria($assignment, $query, $filters))
            ->values();

        $playerCounts = $this->participantCounts($event, $query, $filters);
        $coachCounts = $assignments->groupBy(fn (CoachAssignment $assignment): string => (string) $assignment->event_delegation_id)
            ->map(fn (Collection $items): int => $items->unique('participant_id')->count());
        $hasFilters = $this->hasSummaryFilters($query, $filters);

        $summaryDepartments = $departments->map(function (EventDelegation $department) use ($competitions, $entriesByDepartment, $assignments, $playerCounts, $coachCounts, $query, $filters, $hasFilters): array {
            $departmentEntries = $entriesByDepartment->get((string) $department->getKey(), collect());
            $departmentAssignments = $assignments->where('event_delegation_id', $department->getKey())->values();
            $departmentPeople = $playerCounts->get((string) $department->getKey(), ['players' => 0, 'unassigned' => 0]);
            $departmentCoachCount = (int) ($coachCounts->get((string) $department->getKey()) ?? 0);

            $sports = $competitions->map(function ($competition) use ($department, $departmentEntries, $departmentAssignments, $query, $filters, $hasFilters): array {
                $sportDivisions = $competition->divisions->map(function ($division) use ($department, $departmentEntries, $departmentAssignments, $competition): array {
                    $entry = $departmentEntries
                        ->where('competition_division_id', $division->getKey())
                        ->sortByDesc('id')
                        ->first();
                    $playerCount = $entry === null ? 0 : (int) $entry->active_player_count;
                    $coachCount = $departmentAssignments
                        ->filter(fn (CoachAssignment $assignment): bool => $this->assignmentCoversDivision($assignment, $division->getKey(), $competition->programme_family))
                        ->unique('participant_id')
                        ->count();

                    return [
                        'id' => (string) $division->getKey(),
                        'name' => $division->name,
                        'rosters' => [$this->rosterSummary($entry, $competition, $division, $playerCount, $coachCount)],
                        'counts' => [
                            'rosters' => $entry === null ? 0 : 1,
                            'players' => $playerCount,
                            'coaches' => $coachCount,
                        ],
                    ];
                })->filter(function (array $division) use ($query, $filters, $hasFilters): bool {
                    if (! $hasFilters) return true;
                    if (($filters['roster'] ?? null) === 'unassigned') return false;
                    return $division['counts']['players'] > 0 || $division['counts']['coaches'] > 0;
                })->values();

                $counts = [
                    'rosters' => $sportDivisions->sum(fn (array $division): int => $division['counts']['rosters']),
                    'players' => $sportDivisions->sum(fn (array $division): int => $division['counts']['players']),
                    'coaches' => $sportDivisions->sum(fn (array $division): int => $division['counts']['coaches']),
                ];

                return [
                    'id' => (string) $competition->getKey(),
                    'name' => $competition->name,
                    'programme_family' => $competition->programme_family,
                    'divisions' => $sportDivisions->all(),
                    'counts' => $counts,
                ];
            })->filter(function (array $sport) use ($hasFilters, $filters): bool {
                if (! $hasFilters) return true;
                if (($filters['roster'] ?? null) === 'unassigned') return false;
                return $sport['counts']['players'] > 0 || $sport['counts']['coaches'] > 0;
            })->values();

            return [
                'id' => (string) $department->getKey(),
                'name' => $department->name,
                'abbreviation' => $department->abbreviation,
                'color' => $department->color,
                'counts' => [
                    'players' => (int) $departmentPeople['players'],
                    'unassigned' => (int) $departmentPeople['unassigned'],
                    'rosters' => $sports->sum(fn (array $sport): int => $sport['counts']['rosters']),
                    'coaches' => $departmentCoachCount,
                ],
                'sports' => $sports->all(),
            ];
        })->filter(function (array $department) use ($hasFilters, $filters): bool {
            if (! $hasFilters) return true;
            if (($filters['roster'] ?? null) === 'unassigned') return $department['counts']['unassigned'] > 0;
            return $department['counts']['players'] > 0 || $department['counts']['coaches'] > 0;
        })->values();

        return [
            'departments' => $summaryDepartments->all(),
            'totals' => [
                'players' => $summaryDepartments->sum(fn (array $department): int => $department['counts']['players']),
                'unassigned' => $summaryDepartments->sum(fn (array $department): int => $department['counts']['unassigned']),
                'rosters' => $summaryDepartments->sum(fn (array $department): int => $department['counts']['rosters']),
                'coaches' => $summaryDepartments->sum(fn (array $department): int => $department['counts']['coaches']),
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function previewForEntry(Event $event, EventDelegation $department, Entry $entry, string $view, ?string $query = null, array $filters = []): array
    {
        $entry->loadMissing('division.competition');
        $assignments = $this->matchingAssignments($event, $department, $entry, $query, $filters);
        $personQuery = $event->participants()->where('event_delegation_id', $department->getKey());

        if ($view === 'coaches') {
            $personQuery->whereIn('id', $assignments->pluck('participant_id')->all());
        } else {
            $personQuery
                ->where('is_competitor', true)
                ->whereHas('rosterMembers', fn ($members) => $members->where('entry_id', $entry->getKey())->where('is_active', true)->whereIn('role', $this->playerRoleValues()));
        }

        $this->applyParticipantCriteria($personQuery, $query, $filters, $view !== 'coaches');
        $total = (clone $personQuery)->count();
        $people = $personQuery
            ->with([
                'rosterMembers' => fn ($members) => $members->where('entry_id', $entry->getKey())->with('entry.division.competition'),
                'coachAssignments' => fn ($items) => $items->where('is_active', true)->orderByDesc('id'),
            ])
            ->orderBy('display_name')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return [
            'roster' => $this->previewRoster($event, $department, $entry, $people->count(), $assignments->unique('participant_id')->count()),
            'people' => $people->map(fn (Participant $person): array => $this->previewPerson($person, $view, $assignments))->values()->all(),
            'total' => $total,
            'limit' => self::PREVIEW_LIMIT,
            'has_more' => $total > self::PREVIEW_LIMIT,
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function previewUnassigned(Event $event, EventDelegation $department, string $view, ?string $query = null, array $filters = []): array
    {
        if ($view !== 'players') {
            return ['roster' => null, 'people' => [], 'total' => 0, 'limit' => self::PREVIEW_LIMIT, 'has_more' => false];
        }

        $personQuery = $event->participants()
            ->where('event_delegation_id', $department->getKey())
            ->where('is_competitor', true)
            ->whereDoesntHave('rosterMembers', fn ($members) => $members->where('is_active', true));
        $this->applyParticipantCriteria($personQuery, $query, $filters, true);
        $total = (clone $personQuery)->count();
        $people = $personQuery->orderBy('display_name')->limit(self::PREVIEW_LIMIT)->get();

        return [
            'roster' => [
                'id' => null,
                'name' => 'Not yet rostered',
                'code' => null,
                'state' => 'unassigned',
                'summary' => $total.' player'.($total === 1 ? '' : 's').' waiting for a roster',
                'sport' => null,
                'division' => null,
                'counts' => ['players' => $total, 'coaches' => 0],
            ],
            'people' => $people->map(fn (Participant $person): array => $this->previewPerson($person, 'players', collect()))->values()->all(),
            'total' => $total,
            'limit' => self::PREVIEW_LIMIT,
            'has_more' => $total > self::PREVIEW_LIMIT,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function participantCounts(Event $event, ?string $query, array $filters): Collection
    {
        $base = $event->participants()->where('is_competitor', true);
        $this->applyParticipantCriteria($base, $query, $filters, true);
        $players = (clone $base)
            ->selectRaw('event_delegation_id, COUNT(*) as aggregate')
            ->groupBy('event_delegation_id')
            ->pluck('aggregate', 'event_delegation_id')
            ->map(fn ($count): int => (int) $count);
        $unassigned = (clone $base)
            ->whereDoesntHave('rosterMembers', fn ($members) => $members->where('is_active', true))
            ->selectRaw('event_delegation_id, COUNT(*) as aggregate')
            ->groupBy('event_delegation_id')
            ->pluck('aggregate', 'event_delegation_id')
            ->map(fn ($count): int => (int) $count);

        return $players->keys()->merge($unassigned->keys())->unique()->mapWithKeys(fn ($id): array => [
            (string) $id => [
                'players' => (int) ($players->get($id) ?? 0),
                'unassigned' => (int) ($unassigned->get($id) ?? 0),
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyParticipantCriteria($builder, ?string $query, array $filters, bool $competitorOnly = false): void
    {
        if ($competitorOnly) $builder->where('is_competitor', true);
        if (($filters['roster'] ?? null) === 'assigned') {
            $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true));
        } elseif (($filters['roster'] ?? null) === 'unassigned') {
            $builder->whereDoesntHave('rosterMembers', fn ($members) => $members->where('is_active', true));
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $builder->where('is_active', ($filters['status'] ?? 'active') === 'active');
        }
        if (($filters['sport'] ?? null) !== null && $filters['sport'] !== '') {
            $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true)->whereHas('entry.division', fn ($division) => $division->whereHas('competition', fn ($competition) => $competition->whereKey((int) $filters['sport']))));
        }
        if (($filters['division'] ?? null) !== null && $filters['division'] !== '') {
            $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true)->whereHas('entry', fn ($entry) => $entry->where('competition_division_id', (int) $filters['division'])));
        }
        if ($query !== null && trim($query) !== '') {
            $value = '%'.mb_strtolower(trim($query)).'%';
            $builder->where(fn ($search) => $search->whereRaw('LOWER(display_name) LIKE ?', [$value])->orWhereRaw('LOWER(COALESCE(student_number, \'\')) LIKE ?', [$value]));
        }
    }

    /** @param array<string, mixed> $filters */
    private function assignmentMatchesCriteria(CoachAssignment $assignment, ?string $query, array $filters): bool
    {
        $person = $assignment->participant;
        if ($person === null || ! $person->is_active && (($filters['status'] ?? 'all') === 'active')) return false;
        if (($filters['status'] ?? 'all') === 'inactive' && $person->is_active) return false;
        if ($query !== null && trim($query) !== '' && ! str_contains(mb_strtolower($person->display_name), mb_strtolower(trim($query)))) return false;
        return true;
    }

    private function assignmentCoversDivision(CoachAssignment $assignment, int|string $divisionId, ?string $programmeFamily): bool
    {
        return ($assignment->scope_type?->value ?? (string) $assignment->scope_type) === 'competition_division'
            ? (string) $assignment->scope_key === (string) $divisionId
            : ($programmeFamily !== null && (string) $assignment->scope_key === $programmeFamily);
    }

    /** @return list<string> */
    private function playerRoleValues(): array
    {
        return [RosterMemberRole::StudentAthlete->value, RosterMemberRole::Reserve->value];
    }

    private function hasSummaryFilters(?string $query, array $filters): bool
    {
        return trim((string) $query) !== ''
            || (($filters['status'] ?? 'all') !== 'all')
            || (($filters['roster'] ?? null) !== null && ($filters['roster'] ?? '') !== '')
            || (($filters['sport'] ?? null) !== null && ($filters['sport'] ?? '') !== '')
            || (($filters['division'] ?? null) !== null && ($filters['division'] ?? '') !== '');
    }

    private function rosterSummary(?Entry $entry, $competition, $division, int $playerCount, int $coachCount): array
    {
        return [
            'id' => $entry === null ? null : (string) $entry->getKey(),
            'name' => $entry?->name ?: (($division->name ?: 'Event').' roster'),
            'code' => $entry?->code,
            'state' => $this->entryState($entry),
            'summary' => $entry === null ? 'Roster not created' : $playerCount.' player'.($playerCount === 1 ? '' : 's'),
            'counts' => ['players' => $playerCount, 'coaches' => $coachCount],
            'sport' => ['id' => (string) $competition->getKey(), 'name' => $competition->name],
            'division' => ['id' => (string) $division->getKey(), 'name' => $division->name],
        ];
    }

    private function entryState(?Entry $entry): string
    {
        if ($entry === null) return 'not_started';
        return match ($entry->entryStatus()) {
            EntryStatus::Locked => 'locked',
            EntryStatus::Withdrawn, EntryStatus::Disqualified => 'blocked',
            EntryStatus::Active => 'active',
            default => 'draft',
        };
    }

    /** @param array<string, mixed> $filters */
    private function matchingAssignments(Event $event, EventDelegation $department, Entry $entry, ?string $query, array $filters): Collection
    {
        $entry->loadMissing('division.competition');
        return CoachAssignment::query()
            ->where('event_id', $event->getKey())
            ->where('event_delegation_id', $department->getKey())
            ->where('is_active', true)
            ->with('participant:id,event_delegation_id,display_name,is_active')
            ->get(['id', 'event_id', 'event_delegation_id', 'participant_id', 'coach_type', 'title', 'scope_type', 'scope_key', 'is_active', 'notes'])
            ->filter(fn (CoachAssignment $assignment): bool => $this->assignmentMatchesCriteria($assignment, $query, $filters) && $this->assignmentCoversDivision($assignment, $entry->competition_division_id, $entry->division->competition->programme_family))
            ->values();
    }

    /** @return array<string, mixed> */
    private function previewRoster(Event $event, EventDelegation $department, Entry $entry, int $playerCount, int $coachCount): array
    {
        $entry->loadMissing('division.competition');
        return [
            'id' => (string) $entry->getKey(),
            'name' => $entry->name,
            'code' => $entry->code,
            'state' => $this->entryState($entry),
            'summary' => $playerCount.' player'.($playerCount === 1 ? '' : 's'),
            'sport' => ['id' => (string) $entry->division->competition->getKey(), 'name' => $entry->division->competition->name],
            'division' => ['id' => (string) $entry->division->getKey(), 'name' => $entry->division->name],
            'department' => ['id' => (string) $department->getKey(), 'name' => $department->name],
            'counts' => ['players' => $playerCount, 'coaches' => $coachCount],
        ];
    }

    /** @param Collection<int, CoachAssignment> $assignments @return array<string, mixed> */
    private function previewPerson(Participant $person, string $view, Collection $assignments): array
    {
        $payload = [
            'id' => (string) $person->getKey(),
            'event_delegation_id' => (string) $person->event_delegation_id,
            'display_name' => $person->display_name,
            'given_name' => $person->given_name,
            'family_name' => $person->family_name,
            'student_number' => $person->student_number,
            'email' => $person->email,
            'phone' => $person->phone,
            'private_notes' => $person->private_notes,
            'is_active' => (bool) $person->is_active,
            'is_competitor' => (bool) $person->is_competitor,
        ];
        if ($view === 'coaches') {
            $payload['assignments'] = $person->coachAssignments->map(fn (CoachAssignment $assignment): array => [
                'id' => (string) $assignment->getKey(),
                'coach_type' => $assignment->coach_type->value,
                'title' => $assignment->title,
                'scope_type' => $assignment->scope_type->value,
                'scope_key' => $assignment->scope_key,
                'is_active' => (bool) $assignment->is_active,
                'notes' => $assignment->notes,
            ])->values()->all();
        } else {
            $payload['memberships'] = $person->rosterMembers->map(fn ($member): array => [
                'competition' => $member->entry?->division?->competition?->name,
                'division' => $member->entry?->division?->name,
                'role' => $member->roleType()->value,
                'is_active' => (bool) $member->is_active,
            ])->values()->all();
        }
        return $payload;
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function forEvent(Event $event, ?string $query = null, array $filters = []): array
    {
        $departments = $event->delegations()->where('is_active', true)->orderBy('name')->get();
        $participants = $event->participants()
            ->where('is_competitor', true)
            ->with(['delegation', 'rosterMembers.entry.division.competition'])
            ->when($filters['department'] ?? null, fn ($builder, $id) => $builder->where('event_delegation_id', (int) $id))
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($builder) => $builder->where('is_active', ($filters['status'] ?? 'active') === 'active'))
            ->when(($filters['roster'] ?? null) === 'assigned', fn ($builder) => $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true)))
            ->when(($filters['roster'] ?? null) === 'unassigned', fn ($builder) => $builder->whereDoesntHave('rosterMembers', fn ($members) => $members->where('is_active', true)))
            ->when($filters['sport'] ?? null, fn ($builder, $id) => $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true)->whereHas('entry.division', fn ($division) => $division->whereHas('competition', fn ($competition) => $competition->whereKey((int) $id)))))
            ->when($filters['division'] ?? null, fn ($builder, $id) => $builder->whereHas('rosterMembers', fn ($members) => $members->where('is_active', true)->whereHas('entry', fn ($entry) => $entry->where('competition_division_id', (int) $id))))
            ->when($query !== null && trim($query) !== '', function ($builder) use ($query): void {
                $value = '%'.mb_strtolower(trim($query)).'%';
                $builder->where(fn ($search) => $search->whereRaw('LOWER(display_name) LIKE ?', [$value])->orWhereRaw('LOWER(COALESCE(student_number, \'\')) LIKE ?', [$value]));
            })
            ->orderBy('display_name')
            ->get();

        return $this->departmentSections($departments, $participants, 'participants', $this->hasFilters($query, $filters));
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function coachesForEvent(Event $event, ?string $query = null, array $filters = []): array
    {
        $departments = $event->delegations()->where('is_active', true)->orderBy('name')->get();
        [$divisionIds, $programmeFamily] = $this->coachScope($event, $filters);
        $people = $event->participants()
            ->whereHas('coachAssignments', fn ($assignments) => $assignments->where('is_active', true))
            ->with(['delegation', 'rosterMembers.entry.division.competition', 'coachAssignments' => fn ($assignments) => $assignments->orderByDesc('is_active')->orderBy('id')])
            ->when($filters['department'] ?? null, fn ($builder, $id) => $builder->where('event_delegation_id', (int) $id))
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($builder) => $builder->where('is_active', ($filters['status'] ?? 'active') === 'active'))
            ->when($divisionIds->isNotEmpty() || $programmeFamily !== null, function ($builder) use ($divisionIds, $programmeFamily): void {
                $builder->whereHas('coachAssignments', function ($assignments) use ($divisionIds, $programmeFamily): void {
                    $assignments->where('is_active', true)->where(function ($scope) use ($divisionIds, $programmeFamily): void {
                        if ($divisionIds->isNotEmpty()) {
                            $scope->where(fn ($exact) => $exact->where('scope_type', 'competition_division')->whereIn('scope_key', $divisionIds->map(fn ($id): string => (string) $id)->all()));
                        }
                        if ($programmeFamily !== null) {
                            $scope->orWhere(fn ($family) => $family->where('scope_type', 'programme_family')->where('scope_key', $programmeFamily));
                        }
                    });
                });
            })
            ->when($query !== null && trim($query) !== '', fn ($builder) => $builder->whereRaw('LOWER(display_name) LIKE ?', ['%'.mb_strtolower(trim($query)).'%']))
            ->orderBy('display_name')
            ->get();

        $sections = $this->departmentSections($departments, $people, 'coaches', $this->hasFilters($query, $filters));
        return array_map(function (array $section): array {
            $section['coaches'] = collect($section['coaches'])->map(function (array $coach): array {
                $participant = $coach['_participant'];
                unset($coach['_participant']);
                $coach['assignments'] = $participant->coachAssignments->map(fn ($assignment): array => [
                    'id' => (string) $assignment->getKey(),
                    'coach_type' => $assignment->coach_type->value,
                    'title' => $assignment->title,
                    'scope_type' => $assignment->scope_type->value,
                    'scope_key' => $assignment->scope_key,
                    'is_active' => (bool) $assignment->is_active,
                    'notes' => $assignment->notes,
                ])->values()->all();
                return $coach;
            })->values()->all();
            return $section;
        }, $sections);
    }

    /** @return array{0: \Illuminate\Support\Collection<int, mixed>, 1: ?string} */
    private function coachScope(Event $event, array $filters): array
    {
        $divisionIds = collect();
        $family = null;
        if (! empty($filters['sport'])) {
            $competition = $event->competitions()->with('divisions')->find((int) $filters['sport']);
            $divisionIds = $competition?->divisions?->pluck('id') ?? collect();
            $family = $competition?->programme_family;
        }
        if (! empty($filters['division'])) {
            $division = Division::query()->with('competition')->find((int) $filters['division']);
            $divisionIds = collect([(int) $filters['division']]);
            $family = $division?->competition?->programme_family;
        }
        return [$divisionIds, $family];
    }

    /** @param \Illuminate\Support\Collection<int, EventDelegation> $departments @param \Illuminate\Support\Collection<int, Participant> $people */
    private function departmentSections($departments, $people, string $key, bool $hasFilters): array
    {
        return $departments->map(function (EventDelegation $department) use ($people, $key): array {
            $selected = $people->where('event_delegation_id', $department->getKey())->values();
            $items = $selected->map(function (Participant $participant) use ($key): array {
                $payload = $this->participant($participant);
                if ($key === 'coaches') {
                    $payload['_participant'] = $participant;
                }
                return $payload;
            })->values()->all();
            return ['id' => (string) $department->getKey(), 'name' => $department->name, 'abbreviation' => $department->abbreviation, 'color' => $department->color, 'count' => count($items), $key => $items];
        })->filter(fn (array $section): bool => $section['count'] > 0 || ! $hasFilters)->values()->all();
    }

    private function hasFilters(?string $query, array $filters): bool
    {
        return trim((string) $query) !== '' || collect($filters)->contains(fn ($value): bool => $value !== null && $value !== '' && $value !== 'all');
    }

    /** @return array<string, mixed> */
    private function participant(Participant $participant): array
    {
        return [
            'id' => (string) $participant->getKey(),
            'event_delegation_id' => (string) $participant->event_delegation_id,
            'display_name' => $participant->display_name,
            'given_name' => $participant->given_name,
            'family_name' => $participant->family_name,
            'student_number' => $participant->student_number,
            'email' => $participant->email,
            'phone' => $participant->phone,
            'private_notes' => $participant->private_notes,
            'is_active' => (bool) $participant->is_active,
            'is_competitor' => (bool) $participant->is_competitor,
            'memberships' => $participant->rosterMembers->map(fn ($member): array => [
                'competition' => $member->entry?->division?->competition?->name,
                'division' => $member->entry?->division?->name,
                'role' => $member->roleType()->value,
                'is_active' => (bool) $member->is_active,
            ])->values(),
        ];
    }
}
