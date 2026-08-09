<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Registrations\SaveEntry;
use App\Actions\Registrations\SaveParticipant;
use App\Actions\Registrations\SaveRosterMembership;
use App\Actions\Registrations\SetEligibility;
use App\Actions\Registrations\TransitionEntryStatus;
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
use App\Models\Participant;
use App\Models\RosterMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function index(Request $request, Event $event): Response
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
        ]);
        $event->load([
            'delegations' => fn ($query) => $query->orderBy('name'),
            'competitions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions.governingRuleVersion',
            'competitions.divisions.tournaments',
        ]);

        $participants = $event->participants()
            ->with([
                'delegation',
                'rosterMembers.entry.division.competition',
                'eligibilityRecords',
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $value = '%'.mb_strtolower(trim($search)).'%';
                $query->where(function (Builder $query) use ($value): void {
                    $query->whereRaw('LOWER(display_name) LIKE ?', [$value])
                        ->orWhereRaw('LOWER(COALESCE(given_name, \'\')) LIKE ?', [$value])
                        ->orWhereRaw('LOWER(COALESCE(family_name, \'\')) LIKE ?', [$value])
                        ->orWhereRaw('LOWER(COALESCE(student_number, \'\')) LIKE ?', [$value]);
                });
            })
            ->when($filters['delegation'] ?? null, fn (Builder $query, int $id) => $query->where('event_delegation_id', $id))
            ->when($filters['competition'] ?? null, fn (Builder $query, int $id) => $query->whereHas(
                'rosterMembers.entry.division', fn (Builder $query) => $query->where('competition_id', $id),
            ))
            ->when($filters['division'] ?? null, fn (Builder $query, int $id) => $query->whereHas(
                'rosterMembers.entry', fn (Builder $query) => $query->where('competition_division_id', $id),
            ))
            ->when($filters['entry_mode'] ?? null, fn (Builder $query, string $mode) => $query->whereHas(
                'rosterMembers.entry', fn (Builder $query) => $query->where('entry_mode', $mode),
            ))
            ->when(($filters['roster_status'] ?? null) === 'assigned', fn (Builder $query) => $query->whereHas('rosterMembers', fn (Builder $query) => $query->where('is_active', true)))
            ->when(($filters['roster_status'] ?? null) === 'unassigned', fn (Builder $query) => $query->whereDoesntHave('rosterMembers', fn (Builder $query) => $query->where('is_active', true)))
            ->when($filters['eligibility'] ?? null, fn (Builder $query, string $status) => $query->whereHas(
                'eligibilityRecords', fn (Builder $query) => $query->where('status', $status),
            ))
            ->orderBy('display_name')
            ->get();

        $entries = Entry::query()
            ->whereHas('division.competition', fn (Builder $query) => $query->where('event_id', $event->getKey()))
            ->with([
                'delegation',
                'division.competition',
                'division.governingRuleVersion',
                'division.tournaments',
                'rosterMembers.participant',
                'eligibilityRecords',
            ])
            ->orderBy('name')
            ->get();

        $eligibilityCounts = $event->participants()
            ->join('eligibility_records', 'participants.id', '=', 'eligibility_records.participant_id')
            ->selectRaw('eligibility_records.status, COUNT(*) AS aggregate')
            ->groupBy('eligibility_records.status')
            ->pluck('aggregate', 'status');

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
                'competition' => isset($filters['competition']) ? (string) $filters['competition'] : '',
                'division' => isset($filters['division']) ? (string) $filters['division'] : '',
                'entry_mode' => $filters['entry_mode'] ?? '',
                'roster_status' => $filters['roster_status'] ?? '',
                'eligibility' => $filters['eligibility'] ?? '',
            ],
            'selection' => [
                'participant' => $participants->contains('id', $filters['participant'] ?? null)
                    ? (string) $filters['participant']
                    : null,
                'entry' => $entries->contains('id', $filters['entry'] ?? null)
                    ? (string) $filters['entry']
                    : null,
            ],
            'delegations' => $event->delegations->map(fn ($delegation): array => [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'color' => $delegation->color,
                'active' => (bool) $delegation->is_active,
            ])->values(),
            'competitions' => $event->competitions->map(fn (Competition $competition): array => [
                'id' => (string) $competition->getKey(),
                'name' => $competition->name,
                'divisions' => $competition->divisions->map(fn (Division $division): array => [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                    'participant_mode' => $division->governingRuleVersion?->participantMode()?->value,
                    'min_roster_size' => $division->governingRuleVersion?->min_roster_size,
                    'max_roster_size' => $division->governingRuleVersion?->max_roster_size,
                    'roster_role_limits' => $division->governingRuleVersion?->roster_role_limits ?? [],
                ])->values(),
            ])->values(),
            'participants' => $participants->map(fn (Participant $participant): array => $this->participantData($participant))->values(),
            'entries' => $entries->map(fn (Entry $entry): array => $this->entryData($entry))->values(),
            'summary' => [
                'participants' => $event->participants()->count(),
                'active_participants' => $event->participants()->where('is_active', true)->count(),
                'active_roster_memberships' => RosterMember::query()->whereHas(
                    'entry.division.competition', fn (Builder $query) => $query->where('event_id', $event->getKey()),
                )->where('is_active', true)->count(),
                'eligibility' => collect(EligibilityStatus::cases())->mapWithKeys(
                    fn (EligibilityStatus $status): array => [$status->value => (int) ($eligibilityCounts[$status->value] ?? 0)],
                ),
            ],
            'options' => [
                'entry_modes' => $this->enumOptions(ParticipantMode::cases()),
                'roster_roles' => $this->enumOptions(RosterMemberRole::cases()),
                'eligibility_statuses' => $this->enumOptions(EligibilityStatus::cases()),
                'entry_statuses' => $this->enumOptions(EntryStatus::cases()),
            ],
        ]);
    }

    public function storeParticipant(Request $request, Event $event, SaveParticipant $save): RedirectResponse
    {
        $save->handle($request->user(), $event, $this->participantAttributes($request));

        return back()->with('status', 'Participant registered without creating a login account.');
    }

    public function updateParticipant(Request $request, Event $event, Participant $participant, SaveParticipant $save): RedirectResponse
    {
        $save->handle($request->user(), $event, $this->participantAttributes($request), $participant);

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

    public function transitionEntry(
        Request $request,
        Event $event,
        Entry $entry,
        TransitionEntryStatus $transition,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(EntryStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $transition->handle(
            $request->user(),
            $event,
            $entry,
            EntryStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Entry state updated.');
    }

    /** @return array<string, mixed> */
    private function participantAttributes(Request $request): array
    {
        return $request->validate([
            'event_delegation_id' => ['required', 'integer', 'exists:event_delegations,id'],
            'display_name' => ['required', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'student_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'private_notes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
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
