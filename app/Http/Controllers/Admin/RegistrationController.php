<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Registrations\SaveEntry;
use App\Actions\Registrations\CreateDepartmentRoster;
use App\Actions\Registrations\SaveParticipant;
use App\Actions\Registrations\SaveRosterMembershipBatch;
use App\Actions\Registrations\SaveRosterMembership;
use App\Actions\Registrations\SaveRosterPlayer;
use App\Actions\Registrations\SetEligibilityBatch;
use App\Actions\Registrations\SetEligibility;
use App\Actions\Registrations\TransitionEntryStatus;
use App\Actions\Registrations\SaveCoachAssignment;
use App\Actions\Registrations\DeactivateCoachAssignment;
use App\Actions\Registrations\RecordParticipationException;
use App\Enums\CoachAssignmentScope;
use App\Enums\CoachType;
use App\Enums\ParticipationExceptionType;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\ParticipantMode;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentState;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\CoachAssignment;
use App\Services\ParticipantDirectoryReadModel;
use App\Services\ParticipantCsvImporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function departments(Request $request, Event $event, ParticipantDirectoryReadModel $directory): Response
    {
        $this->assertAdmin($request, $event);

        return Inertia::render('Admin/Departments/Index', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'archived' => $event->isArchived(),
            ],
            'directory_summary' => $directory->summaryForEvent($event),
        ]);
    }

    public function department(
        Request $request,
        Event $event,
        EventDelegation $department,
        ParticipantDirectoryReadModel $directory,
    ): Response {
        $this->assertAdmin($request, $event);
        abort_unless(
            (int) $department->event_id === (int) $event->getKey() && (bool) $department->is_active,
            404,
        );

        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['players', 'coaches'])],
        ]);
        $event->load([
            'delegations' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'competitions' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'competitions.divisions' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
        ]);
        $summary = $directory->summaryForEvent($event);
        $selectedDepartment = collect($summary['departments'])
            ->firstWhere('id', (string) $department->getKey());
        abort_if($selectedDepartment === null, 404);

        return Inertia::render('Admin/Departments/Show', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'archived' => $event->isArchived(),
            ],
            'department' => $selectedDepartment,
            'departments' => $event->delegations->map(fn (EventDelegation $delegation): array => [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'color' => $delegation->color,
            ])->values(),
            'competitions' => $event->competitions->map(fn (Competition $competition): array => [
                'id' => (string) $competition->getKey(),
                'name' => $competition->name,
                'programme_family' => $competition->programme_family,
                'divisions' => $competition->divisions->map(fn (Division $division): array => [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                ])->values(),
            ])->values(),
            'initial_view' => $filters['view'] ?? 'players',
        ]);
    }

    public function index(Request $request, Event $event, ParticipantDirectoryReadModel $directory): Response|RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'delegation' => ['nullable', 'integer'],
            'competition' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
            'entry_mode' => ['nullable', Rule::enum(ParticipantMode::class)],
            'roster_status' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'eligibility' => ['nullable', Rule::enum(EligibilityStatus::class)],
            'participant' => ['nullable', 'integer'],
            'entry' => ['nullable', 'integer'],
            'view' => ['nullable', Rule::in(['players', 'coaches'])],
            'directory_department' => ['nullable', 'integer'],
            'directory_sport' => ['nullable', 'integer'],
            'directory_division' => ['nullable', 'integer'],
            'directory_entry' => ['nullable', 'integer'],
            'directory_roster' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'directory_status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);
        $directoryFilters = [
            'roster' => $filters['directory_roster'] ?? $filters['roster_status'] ?? null,
            'status' => $filters['directory_status'] ?? 'all',
        ];
        $event->load([
            'delegations' => fn ($query) => $query->orderBy('name'),
            'competitions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions.governingRuleVersion',
            'competitions.divisions.tournaments',
        ]);

        $directorySport = ($filters['directory_sport'] ?? null) === null
            ? null
            : $event->competitions->firstWhere('id', (int) $filters['directory_sport']);
        $directoryDivision = ($filters['directory_division'] ?? null) === null
            ? null
            : $event->competitions->flatMap->divisions->firstWhere('id', (int) $filters['directory_division']);
        if (($filters['directory_sport'] ?? null) !== null && $directorySport === null) {
            abort(404);
        }
        if (($filters['directory_division'] ?? null) !== null && ($directoryDivision === null || ($directorySport !== null && (int) $directoryDivision->competition_id !== (int) $directorySport->getKey()))) {
            abort(404);
        }

        $scopedCompetition = ($filters['competition'] ?? null) === null
            ? null
            : $event->competitions->firstWhere('id', (int) $filters['competition']);
        $scopedDivision = ($filters['division'] ?? null) === null
            ? null
            : $event->competitions->flatMap->divisions->firstWhere('id', (int) $filters['division']);
        if (($filters['competition'] ?? null) !== null && $scopedCompetition === null) {
            abort(404);
        }
        if (($filters['division'] ?? null) !== null && ($scopedDivision === null || ($scopedCompetition !== null && (int) $scopedDivision->competition_id !== (int) $scopedCompetition->getKey()))) {
            abort(404);
        }

        $selectedEntry = ($filters['entry'] ?? null) === null
            ? null
            : Entry::query()->with('division.competition')->find((int) $filters['entry']);
        if (($filters['entry'] ?? null) !== null && ($selectedEntry === null || $selectedEntry->eventId() !== (int) $event->getKey())) {
            abort(404);
        }
        if ($selectedEntry !== null && $scopedCompetition !== null && (int) $selectedEntry->division->competition_id !== (int) $scopedCompetition->getKey()) {
            abort(404);
        }
        if ($selectedEntry !== null && $scopedDivision !== null && (int) $selectedEntry->competition_division_id !== (int) $scopedDivision->getKey()) {
            abort(404);
        }

        if ($scopedCompetition !== null || $scopedDivision !== null || $selectedEntry !== null) {
            $division = $selectedEntry?->division ?? $scopedDivision;
            $competition = $selectedEntry?->division?->competition ?? $scopedCompetition ?? $division?->competition;
            abort_if($competition === null, 404);

            return redirect()->route('admin.sports.show', array_filter([
                'event' => $event,
                'sport' => $competition,
                'tab' => 'rosters',
                'division' => $division?->getKey(),
                'department' => $selectedEntry?->event_delegation_id,
            ], fn ($value): bool => $value !== null));
        }

        $directoryDepartment = ($filters['directory_department'] ?? $filters['delegation'] ?? null) === null
            ? null
            : $event->delegations()->whereKey((int) ($filters['directory_department'] ?? $filters['delegation']))->where('is_active', true)->first();
        if (($filters['directory_department'] ?? $filters['delegation'] ?? null) !== null && $directoryDepartment === null) {
            abort(404);
        }

        $directorySport = ($filters['directory_sport'] ?? null) === null
            ? null
            : $event->competitions->firstWhere('id', (int) $filters['directory_sport']);
        if (($filters['directory_sport'] ?? null) !== null && $directorySport === null) {
            abort(404);
        }

        $directoryDivision = ($filters['directory_division'] ?? null) === null
            ? null
            : $event->competitions->flatMap->divisions->firstWhere('id', (int) $filters['directory_division']);
        if (($filters['directory_division'] ?? null) !== null && ($directoryDivision === null || ($directorySport !== null && (int) $directoryDivision->competition_id !== (int) $directorySport->getKey()))) {
            abort(404);
        }

        $directoryEntry = ($filters['directory_entry'] ?? null) === null
            ? null
            : Entry::query()->with('division.competition')->find((int) $filters['directory_entry']);
        if ($directoryEntry !== null) {
            if ($directoryEntry->eventId() !== (int) $event->getKey()) abort(404);
            if ($directoryDepartment !== null && (int) $directoryEntry->event_delegation_id !== (int) $directoryDepartment->getKey()) abort(404);
            if ($directoryDivision !== null && (int) $directoryEntry->competition_division_id !== (int) $directoryDivision->getKey()) abort(404);
            if ($directorySport !== null && (int) $directoryEntry->division->competition_id !== (int) $directorySport->getKey()) abort(404);
        }

        $directorySummary = $directory->summaryForEvent($event, $filters['q'] ?? null, $directoryFilters);
        $selectedDepartment = $directoryDepartment?->getKey() ?? data_get($directorySummary, 'departments.0.id');

        return Inertia::render('Admin/Registrations/Index', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'archived' => $event->isArchived(),
            ],
            'filters' => [
                'q' => $filters['q'] ?? '',
                'delegation' => isset($filters['delegation']) ? (string) $filters['delegation'] : '',
                'roster_status' => $filters['roster_status'] ?? '',
                'view' => $filters['view'] ?? 'players',
                'directory_department' => isset($filters['directory_department']) ? (string) $filters['directory_department'] : (isset($filters['delegation']) ? (string) $filters['delegation'] : ''),
                'directory_sport' => isset($filters['directory_sport']) ? (string) $filters['directory_sport'] : '',
                'directory_division' => isset($filters['directory_division']) ? (string) $filters['directory_division'] : '',
                'directory_entry' => isset($filters['directory_entry']) ? (string) $filters['directory_entry'] : '',
                'directory_roster' => $filters['directory_roster'] ?? ($filters['roster_status'] ?? ''),
                'directory_status' => $filters['directory_status'] ?? 'all',
            ],
            'selection' => [
                'department' => $selectedDepartment === null ? '' : (string) $selectedDepartment,
                'sport' => $directorySport === null ? '' : (string) $directorySport->getKey(),
                'division' => $directoryDivision === null ? '' : (string) $directoryDivision->getKey(),
                'entry' => $directoryEntry === null ? '' : (string) $directoryEntry->getKey(),
            ],
            'delegations' => $event->delegations->where('is_active', true)->map(fn ($delegation): array => [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'color' => $delegation->color,
                'active' => (bool) $delegation->is_active,
            ])->values(),
            'competitions' => $event->competitions->map(fn (Competition $competition): array => [
                'id' => (string) $competition->getKey(),
                'name' => $competition->name,
                'programme_family' => $competition->programme_family,
                'active' => (bool) $competition->is_active,
                'divisions' => $competition->divisions->map(fn (Division $division): array => [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                    'active' => (bool) $division->is_active,
                    'participant_mode' => $division->governingRuleVersion?->participantMode()?->value,
                    'min_roster_size' => $division->governingRuleVersion?->min_roster_size,
                    'max_roster_size' => $division->governingRuleVersion?->max_roster_size,
                    'roster_role_limits' => $division->governingRuleVersion?->roster_role_limits ?? [],
                ])->values(),
            ])->values(),
            'directory_summary' => $directorySummary,
            // Compatibility keys intentionally stay empty so older page shells
            // do not trigger a second, unscoped participant query.
            'participants' => [],
            'sections' => [],
            'coach_sections' => [],
        ]);
    }

    public function directoryPreview(Request $request, Event $event, ParticipantDirectoryReadModel $directory): JsonResponse
    {
        $this->assertAdmin($request, $event);
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['players', 'coaches'])],
            'department' => ['required', 'integer'],
            'sport' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
            'entry' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'roster' => ['nullable', Rule::in(['assigned', 'unassigned'])],
        ]);
        $view = $filters['view'] ?? 'players';
        $department = $event->delegations()->whereKey((int) $filters['department'])->where('is_active', true)->firstOrFail();
        $sport = ($filters['sport'] ?? null) === null ? null : $event->competitions()->whereKey((int) $filters['sport'])->first();
        if (($filters['sport'] ?? null) !== null && $sport === null) abort(404);
        $division = ($filters['division'] ?? null) === null
            ? null
            : Division::query()->whereKey((int) $filters['division'])->where('competition_id', $sport?->getKey())->first();
        if (($filters['division'] ?? null) !== null && $division === null) abort(404);
        $entry = ($filters['entry'] ?? null) === null ? null : Entry::query()->with('division.competition')->find((int) $filters['entry']);
        if ($entry !== null) {
            if ($entry->eventId() !== (int) $event->getKey()) abort(404);
            if ((int) $entry->event_delegation_id !== (int) $department->getKey()) abort(404);
            if ($division !== null && (int) $entry->competition_division_id !== (int) $division->getKey()) abort(404);
            if ($sport !== null && (int) $entry->division->competition_id !== (int) $sport->getKey()) abort(404);
            return response()->json($directory->previewForEntry($event, $department, $entry, $view, $filters['q'] ?? null, [
                'status' => $filters['status'] ?? 'all',
                'roster' => $filters['roster'] ?? null,
            ]));
        }

        if (($filters['roster'] ?? null) === 'unassigned' && $view === 'players') {
            return response()->json($directory->previewUnassigned($event, $department, $view, $filters['q'] ?? null, [
                'status' => $filters['status'] ?? 'all',
                'roster' => 'unassigned',
            ]));
        }

        abort(422, 'A roster is required to preview directory people.');
    }

    public function storeParticipant(Request $request, Event $event, SaveParticipant $save): RedirectResponse
    {
        $attributes = $this->participantAttributes($request);
        $entry = isset($attributes['entry_id']) && $attributes['entry_id'] !== null
            ? Entry::query()->with('division.competition')->findOrFail((int) $attributes['entry_id'])
            : null;
        if ($entry !== null) {
            if ($entry->eventId() !== (int) $event->getKey() || $event->isArchived()) {
                throw new AuthorizationException('The selected roster does not belong to this Event.');
            }
            $entry->loadMissing('division.tournaments');
            if ($entry->isLocked() || $entry->division->tournaments->contains(fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published)) {
                throw ValidationException::withMessages(['entry_id' => 'A locked or published roster cannot accept new player profiles from this panel.']);
            }
            $attributes['event_delegation_id'] = (int) $entry->event_delegation_id;
        } elseif (empty($attributes['event_delegation_id'])) {
            throw ValidationException::withMessages(['event_delegation_id' => 'Choose a department for this player.']);
        }
        unset($attributes['entry_id']);
        $participant = $save->handle($request->user(), $event, $attributes);

        if ($entry !== null) {
            return $this->rosterRedirect($event, $entry)->with('selected_participant_ids', [(string) $participant->getKey()])->with('status', 'Player profile created. Add them to the roster when ready.');
        }

        return back()->with('status', 'Participant registered without creating a login account.');
    }

    public function storeDepartmentRoster(
        Request $request,
        Event $event,
        Division $division,
        EventDelegation $department,
        CreateDepartmentRoster $create,
    ): RedirectResponse {
        $entry = $create->handle($request->user(), $event, $division, $department);

        return $this->rosterRedirect($event, $entry)->with('status', 'Roster created.');
    }

    public function inspectParticipantImport(Request $request, Event $event, ParticipantCsvImporter $importer): \Illuminate\Http\JsonResponse
    {
        $this->assertWritable($request, $event);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'department_id' => ['nullable', 'integer'],
            'entry_id' => ['nullable', 'integer'],
            'mapping' => ['nullable', 'array'],
        ]);
        $department = $this->importDepartment($event, $data['department_id'] ?? null, $data['entry_id'] ?? null);
        $preview = $importer->inspect($data['file'], $event, $department, $data['mapping'] ?? []);

        return response()->json($preview);
    }

    public function confirmParticipantImport(
        Request $request,
        Event $event,
        ParticipantCsvImporter $importer,
        SaveParticipant $save,
    ): \Illuminate\Http\JsonResponse|RedirectResponse {
        $this->assertWritable($request, $event);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'department_id' => ['nullable', 'integer'],
            'entry_id' => ['nullable', 'integer'],
            'mapping' => ['nullable', 'array'],
        ]);
        $department = $this->importDepartment($event, $data['department_id'] ?? null, $data['entry_id'] ?? null);
        $result = $importer->import($request->user(), $event, $data['file'], $department, $data['mapping'] ?? [], $save);
        $selected = array_values(array_unique([...$result['created_ids'], ...$result['existing_ids']]));
        $request->session()->flash('selected_participant_ids', $selected);
        $payload = [...$result, 'selected_participant_ids' => $selected, 'status' => "Imported {$result['count']} player profile".($result['count'] === 1 ? '' : 's').'.'];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('status', $payload['status']);
    }

    public function updateParticipant(Request $request, Event $event, Participant $participant, SaveParticipant $save): RedirectResponse
    {
        $attributes = $this->participantAttributes($request);
        $attributes['event_delegation_id'] ??= $participant->event_delegation_id;
        $save->handle($request->user(), $event, $attributes, $participant);

        return back()->with('status', 'Participant profile updated.');
    }

    public function storeEntry(Request $request, Event $event, SaveEntry $save): RedirectResponse
    {
        $save->handle($request->user(), $event, $this->entryAttributes($request));

        return back()->with('status', 'Division Entry created as a draft.');
    }

    public function updateEntry(Request $request, Event $event, Entry $entry, SaveEntry $save): RedirectResponse
    {
        $save->handle($request->user(), $event, $this->entryAttributes($request), $entry);

        return back()->with('status', 'Division Entry updated.');
    }

    public function saveMembership(
        Request $request,
        Event $event,
        Entry $entry,
        Participant $participant,
        SaveRosterMembership $save,
    ): RedirectResponse {
        $data = $request->validate([
            'role' => ['required', Rule::enum(RosterMemberRole::class)],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $save->handle(
            $request->user(),
            $event,
            $entry,
            $participant,
            RosterMemberRole::from($data['role']),
            (bool) $data['is_active'],
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Roster membership saved. Redraw any existing preview before publication.');
    }

    public function updateRosterPlayer(
        Request $request,
        Event $event,
        Entry $entry,
        Participant $participant,
        SaveRosterPlayer $save,
    ): RedirectResponse {
        $data = $request->validate([
            'profile' => ['sometimes', 'array'],
            'profile.display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'profile.given_name' => ['nullable', 'string', 'max:255'],
            'profile.family_name' => ['nullable', 'string', 'max:255'],
            'profile.student_number' => ['nullable', 'string', 'max:100'],
            'profile.email' => ['nullable', 'email', 'max:255'],
            'profile.phone' => ['nullable', 'string', 'max:80'],
            'profile.private_notes' => ['nullable', 'string', 'max:4000'],
            'membership' => ['sometimes', 'array'],
            'membership.role' => ['sometimes', Rule::enum(RosterMemberRole::class)],
            'membership.is_active' => ['sometimes', 'boolean'],
            'membership.notes' => ['nullable', 'string', 'max:1000'],
            'eligibility' => ['sometimes', 'array'],
            'eligibility.status' => ['sometimes', Rule::enum(EligibilityStatus::class)],
            'eligibility.reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $save->handle($request->user(), $event, $entry, $participant, $data);

        return $this->rosterRedirect($event, $entry)->with('status', 'Player changes saved.');
    }

    public function saveMembershipBatch(
        Request $request,
        Event $event,
        Entry $entry,
        SaveRosterMembershipBatch $save,
    ): RedirectResponse {
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:100'],
            'members.*.participant_id' => ['required', 'integer', 'distinct'],
            'members.*.role' => ['required', Rule::enum(RosterMemberRole::class)],
        ]);
        $save->handle($request->user(), $event, $entry, $data['members']);

        return $this->rosterRedirect($event, $entry)->with('status', 'Players added to roster. Eligibility is pending review.');
    }

    public function setEligibility(
        Request $request,
        Event $event,
        Entry $entry,
        Participant $participant,
        SetEligibility $set,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(EligibilityStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $set->handle(
            $request->user(),
            $event,
            $entry,
            $participant,
            EligibilityStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Eligibility decision recorded with its actor and time.');
    }

    public function setEligibilityBatch(
        Request $request,
        Event $event,
        Entry $entry,
        SetEligibilityBatch $set,
    ): RedirectResponse {
        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1', 'max:100'],
            'participant_ids.*' => ['required', 'integer', 'distinct'],
            'status' => ['required', Rule::enum(EligibilityStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
        ]);
        $set->handle(
            $request->user(),
            $event,
            $entry,
            array_map('intval', $data['participant_ids']),
            EligibilityStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return $this->rosterRedirect($event, $entry)->with('status', 'Eligibility decisions recorded.');
    }

    public function transitionEntry(
        Request $request,
        Event $event,
        Entry $entry,
        TransitionEntryStatus $transition,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(EntryStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'roster_review_confirmed' => ['nullable', 'boolean'],
        ]);
        $transition->handle(
            $request->user(),
            $event,
            $entry,
            EntryStatus::from($data['status']),
            $data['reason'] ?? null,
            (bool) ($data['roster_review_confirmed'] ?? false),
        );

        return $this->rosterRedirect($event, $entry)->with('status', 'Entry state updated.');
    }

    public function saveCoachAssignment(Request $request, Event $event, Participant $participant, SaveCoachAssignment $save): RedirectResponse
    {
        $data = $request->validate([
            'coach_type' => ['required', Rule::enum(CoachType::class)],
            'title' => ['nullable', Rule::in(['Coach', 'Head Coach', 'Assistant Coach', 'Trainer', 'Team Captain'])],
            'scope_type' => ['required', Rule::enum(CoachAssignmentScope::class)],
            'scope_key' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $save->handle($request->user(), $event, $participant, CoachType::from($data['coach_type']), CoachAssignmentScope::from($data['scope_type']), $data['scope_key'], $data['title'] ?? null, $data['notes'] ?? null);
        return back()->with('status', 'Coach assignment saved.');
    }

    public function storeRosterCoachSupport(
        Request $request,
        Event $event,
        Division $division,
        EventDelegation $department,
        SaveParticipant $participants,
        SaveCoachAssignment $assignments,
    ): RedirectResponse {
        $this->assertWritable($request, $event);
        $division->loadMissing('competition');
        abort_unless(
            (int) $division->competition->event_id === (int) $event->getKey()
                && (int) $department->event_id === (int) $event->getKey()
                && (bool) $department->is_active,
            404,
        );

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'student_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'private_notes' => ['nullable', 'string', 'max:4000'],
            'coach_type' => ['required', Rule::enum(CoachType::class)],
            'title' => ['nullable', Rule::in(['Coach', 'Head Coach', 'Assistant Coach', 'Trainer', 'Team Captain'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $event, $division, $department, $participants, $assignments, $data): void {
            $participant = $participants->handle($request->user(), $event, [
                'event_delegation_id' => $department->getKey(),
                'display_name' => $data['display_name'],
                'given_name' => $data['given_name'] ?? null,
                'family_name' => $data['family_name'] ?? null,
                'student_number' => $data['student_number'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'private_notes' => $data['private_notes'] ?? null,
                'is_active' => true,
                'is_competitor' => false,
            ]);
            $assignments->handle(
                $request->user(),
                $event,
                $participant,
                CoachType::from($data['coach_type']),
                CoachAssignmentScope::Division,
                (string) $division->getKey(),
                $data['title'] ?? null,
                $data['notes'] ?? null,
            );
        });

        return redirect()->route('admin.sports.show', [
            'event' => $event,
            'sport' => $division->competition,
            'tab' => 'rosters',
            'division' => $division,
            'department' => $department,
        ])->with('status', 'Coach or support person added to this roster.');
    }

    public function deactivateCoachAssignment(Request $request, Event $event, CoachAssignment $assignment, DeactivateCoachAssignment $deactivate): RedirectResponse
    {
        $deactivate->handle($request->user(), $event, $assignment);
        return back()->with('status', 'Coach assignment removed from active coverage.');
    }

    public function recordParticipationException(Request $request, Event $event, Entry $entry, Participant $participant, RecordParticipationException $record): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::enum(ParticipationExceptionType::class), Rule::notIn([ParticipationExceptionType::Ineligible->value])], 'reason' => ['required', 'string', 'max:2000']]);
        $record->handle($request->user(), $event, $entry, $participant, ParticipationExceptionType::from($data['type']), $data['reason']);
        return $this->rosterRedirect($event, $entry)->with('status', 'Participation exception recorded.');
    }

    /** @return array<string, mixed> */
    private function participantAttributes(Request $request): array
    {
        return $request->validate([
            'entry_id' => ['nullable', 'integer', 'exists:entries,id'],
            'event_delegation_id' => ['nullable', 'integer', 'exists:event_delegations,id'],
            'display_name' => ['required', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'student_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'private_notes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
            'is_competitor' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function entryAttributes(Request $request): array
    {
        return $request->validate([
            'competition_division_id' => ['required', 'integer', 'exists:competition_divisions,id'],
            'event_delegation_id' => ['required', 'integer', 'exists:event_delegations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'entry_mode' => ['required', Rule::enum(ParticipantMode::class)],
        ]);
    }

    private function assertAdmin(Request $request, Event $event): void
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('The active Global Admin is required.');
        }
    }

    private function assertWritable(Request $request, Event $event): void
    {
        $this->assertAdmin($request, $event);
        if ($event->isArchived()) {
            throw new AuthorizationException('Archived events are read-only.');
        }
    }

    private function importDepartment(Event $event, mixed $departmentId, mixed $entryId = null): ?EventDelegation
    {
        if ($entryId !== null && $entryId !== '') {
            $entry = Entry::query()->with(['division.competition', 'division.tournaments'])->findOrFail((int) $entryId);
            if ($entry->eventId() !== (int) $event->getKey()) {
                throw new AuthorizationException('The selected roster does not belong to this Event.');
            }
            if ($entry->isLocked() || $entry->division->tournaments->contains(fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published)) {
                throw ValidationException::withMessages(['entry_id' => 'A locked or published roster cannot accept imports.']);
            }
            if ($departmentId !== null && (int) $departmentId !== (int) $entry->event_delegation_id) {
                throw new AuthorizationException('The import Department must match the selected roster.');
            }
            $departmentId = $entry->event_delegation_id;
        }
        if ($departmentId === null || $departmentId === '') {
            return null;
        }
        $department = $event->delegations()->where('is_active', true)->whereKey((int) $departmentId)->first();
        if ($department === null) {
            throw new AuthorizationException('The selected Department does not belong to this Event.');
        }
        return $department;
    }

    private function rosterRedirect(Event $event, Entry $entry): RedirectResponse
    {
        $entry->loadMissing('division.competition');

        return redirect()->route('admin.sports.show', [
            'event' => $event,
            'sport' => $entry->division->competition,
            'tab' => 'rosters',
            'division' => $entry->competition_division_id,
            'department' => $entry->event_delegation_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function participantData(Participant $participant): array
    {
        return [
            'id' => (string) $participant->getKey(),
            'event_delegation_id' => (string) $participant->event_delegation_id,
            'delegation' => $participant->delegation?->abbreviation,
            'display_name' => $participant->display_name,
            'given_name' => $participant->given_name,
            'family_name' => $participant->family_name,
            'student_number' => $participant->student_number,
            'email' => $participant->email,
            'phone' => $participant->phone,
            'private_notes' => $participant->private_notes,
            'is_active' => (bool) $participant->is_active,
            'memberships' => $participant->rosterMembers->map(function (RosterMember $member) use ($participant): array {
                $eligibility = $participant->eligibilityRecords->firstWhere('entry_id', $member->entry_id);

                return [
                    'entry_id' => (string) $member->entry_id,
                    'entry' => $member->entry?->name,
                    'competition' => $member->entry?->division?->competition?->name,
                    'division' => $member->entry?->division?->name,
                    'role' => $member->roleType()->value,
                    'is_active' => (bool) $member->is_active,
                    'eligibility' => $eligibility?->eligibilityStatus()->value ?? 'pending',
                    'eligibility_reason' => $eligibility?->reason,
                ];
            })->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function entryData(Entry $entry): array
    {
        $rule = $entry->division?->governingRuleVersion;
        $preview = $entry->division?->tournaments->contains(fn ($tournament): bool => in_array(
            $tournament->tournamentState(), [TournamentState::Preview, TournamentState::Uncontested], true,
        )) ?? false;
        $published = $entry->division?->tournaments->contains(
            fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published,
        ) ?? false;

        return [
            'id' => (string) $entry->getKey(),
            'competition_division_id' => (string) $entry->competition_division_id,
            'event_delegation_id' => (string) $entry->event_delegation_id,
            'competition' => $entry->division?->competition?->name,
            'division' => $entry->division?->name,
            'delegation' => $entry->delegation?->abbreviation,
            'name' => $entry->name,
            'code' => $entry->code,
            'entry_mode' => $entry->entryMode()->value,
            'status' => $entry->entryStatus()->value,
            'status_reason' => $entry->status_reason,
            'locked_at' => $entry->locked_at?->toIso8601String(),
            'has_preview' => $preview,
            'published' => $published,
            'limits' => [
                'minimum' => $rule?->min_roster_size,
                'maximum' => $rule?->max_roster_size,
                'roles' => $rule?->roster_role_limits ?? [],
            ],
            'members' => $entry->rosterMembers->map(function (RosterMember $member) use ($entry): array {
                $eligibility = $entry->eligibilityRecords->firstWhere('participant_id', $member->participant_id);

                return [
                    'participant_id' => (string) $member->participant_id,
                    'name' => $member->participant?->display_name,
                    'role' => $member->roleType()->value,
                    'is_active' => (bool) $member->is_active,
                    'notes' => $member->notes,
                    'eligibility' => $eligibility?->eligibilityStatus()->value ?? 'pending',
                    'eligibility_reason' => $eligibility?->reason,
                ];
            })->values(),
        ];
    }

    /** @param array<int, \BackedEnum> $cases */
    private function enumOptions(array $cases): array
    {
        return array_map(static fn ($case): array => [
            'value' => $case->value,
            'label' => Str::headline($case->value),
        ], $cases);
    }
}
