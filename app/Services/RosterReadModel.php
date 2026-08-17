<?php

namespace App\Services;

use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;

final class RosterReadModel
{
    /** @var list<RosterMemberRole> */
    private const PLAYER_ROLES = [RosterMemberRole::StudentAthlete, RosterMemberRole::Reserve];

    /** @var list<RosterMemberRole> */
    private const STAFF_ROLES = [RosterMemberRole::StudentCoach, RosterMemberRole::FacultyCoach];

    public function __construct(private readonly RosterReadiness $readiness, private readonly CoachAssignmentResolver $coaches) {}

    /** @return array<string, mixed> */
    public function forDivision(Event $event, Competition $sport, Division $division, ?EventDelegation $department = null): array
    {
        $event->loadMissing(['delegations' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);
        $division->loadMissing([
            'governingRuleVersion',
            'tournaments',
            'entries.delegation',
            'entries.rosterMembers.participant',
            'entries.rosterApprovals',
        ]);

        $departments = $event->delegations->values()->map(function (EventDelegation $delegation) use ($division): array {
            $entry = $this->currentEntry($division, $delegation);
            $readiness = $entry === null ? ['ready' => false, 'blockers' => [], 'notices' => []] : $this->readiness->forEntry($entry);
            $athleteCount = $entry?->rosterMembers->filter(function ($member): bool {
                return (bool) $member->is_active && in_array($member->roleType(), [RosterMemberRole::StudentAthlete, RosterMemberRole::Reserve], true);
            })->count() ?? 0;
            $maximum = $division->governingRuleVersion?->max_roster_size;
            $state = $this->state($entry, $readiness, $division);

            return [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'color' => $delegation->color,
                'entry_id' => $entry === null ? null : (string) $entry->getKey(),
                'state' => $state,
                'summary' => $entry === null
                    ? 'Roster not started'
                    : ($maximum === null ? "{$athleteCount} players" : "{$athleteCount} of {$maximum} players"),
                'attention' => $this->attention($entry, $readiness),
            ];
        });

        $selected = $department === null ? null : $this->selected($event, $division, $department);

        return [
            'departments' => $departments,
            'selected' => $selected,
        ];
    }

    /** @return array<string, mixed> */
    private function selected(Event $event, Division $division, EventDelegation $department): array
    {
        $entry = $this->currentEntry($division, $department);
        $participants = $event->participants()
            ->where('event_delegation_id', $department->getKey())
            ->when($entry !== null, function ($query) use ($entry): void {
                $query->where(function ($query) use ($entry): void {
                    $query->where('is_active', true)
                        ->orWhereHas('rosterMembers', fn ($query) => $query->where('entry_id', $entry->getKey()));
                });
            }, fn ($query) => $query->where('is_active', true))
            ->where('is_competitor', true)
            ->with(['delegation', 'rosterMembers.entry.division.competition', 'participationExceptions'])
            ->orderBy('display_name')
            ->get();
        $readiness = $entry === null ? ['ready' => false, 'blockers' => [], 'notices' => []] : $this->readiness->forEntry($entry);

        $entryPayload = $entry === null ? null : $this->entry($entry, $event);
        $participantPayload = $participants->map(fn (Participant $participant): array => $this->participant($participant, $entry, $event))->values();
        $active = $participantPayload->filter(fn (array $participant): bool => $participant['membership']['is_active'] ?? false);
        $activePlayers = $active->filter(fn (array $participant): bool => in_array($participant['membership']['role'] ?? null, array_map(fn (RosterMemberRole $role): string => $role->value, self::PLAYER_ROLES), true));
        $coaches = $entry === null ? collect() : $this->coaches->forEntry($entry)->map(fn ($assignment): array => [
            'id' => (string) $assignment->participant_id,
            'display_name' => $assignment->participant?->display_name,
            'coach_type' => $assignment->coach_type->value,
            'title' => $assignment->title ?: 'Coach',
            'scope_type' => $assignment->scope_type->value,
            'scope_key' => $assignment->scope_key,
        ])->values();

        return [
            'department_id' => (string) $department->getKey(),
            'entry' => $entryPayload,
            'participants' => $participantPayload,
            'coaches' => $coaches,
            'counts' => [
                'active_players' => $activePlayers->count(),
                'team_staff' => $coaches->count(),
                'history' => $participantPayload->filter(fn (array $participant): bool => ($participant['membership']['is_active'] ?? true) === false)->count(),
            ],
            'readiness' => $readiness,
        ];
    }

    private function currentEntry(Division $division, EventDelegation $department): ?Entry
    {
        return $division->entries
            ->filter(fn (Entry $entry): bool => (int) $entry->event_delegation_id === (int) $department->getKey())
            ->sortByDesc('id')
            ->first();
    }

    /** @param array{ready: bool, blockers: list<string>, notices: list<string>} $readiness */
    private function state(?Entry $entry, array $readiness, Division $division): string
    {
        if ($entry === null) {
            return 'not_started';
        }
        if ($entry->entryStatus() === EntryStatus::Locked) {
            return 'locked';
        }
        if (in_array($entry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)) {
            return 'blocked';
        }
        if ($readiness['ready']) {
            return 'ready';
        }
        $hasPublishedTournament = $division->tournaments->contains(fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published);
        $hasMissingRule = $division->governingRuleVersion === null;
        return $hasMissingRule || $hasPublishedTournament ? 'blocked' : 'review';
    }

    /** @param array{ready: bool, blockers: list<string>, notices: list<string>} $readiness */
    private function attention(?Entry $entry, array $readiness): ?string
    {
        if ($entry === null) {
            return 'Roster not created';
        }
        if ($entry->isLocked()) {
            return null;
        }
        if ($readiness['blockers'] !== []) {
            return count($readiness['blockers']).' item'.(count($readiness['blockers']) === 1 ? '' : 's').' to resolve';
        }
        return $readiness['notices'][0] ?? null;
    }

    /** @return array<string, mixed> */
    private function entry(Entry $entry, Event $event): array
    {
        $rule = $entry->division->governingRuleVersion;
        $published = $entry->division->tournaments->contains(
            fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published,
        );
        $locked = $entry->isLocked();
        $blocked = in_array($entry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true);
        return [
            'id' => (string) $entry->getKey(),
            'name' => $entry->name,
            'code' => $entry->code,
            'status' => $entry->entryStatus()->value,
            'entry_mode' => $entry->entryMode()->value,
            'locked_at' => $entry->locked_at?->toIso8601String(),
            'approval_revision' => (int) ($entry->rosterApprovals->max('revision') ?? 0),
            'limits' => [
                'minimum' => $rule?->min_roster_size,
                'maximum' => $rule?->max_roster_size,
                'roles' => $rule?->roster_role_limits ?? [],
            ],
            'members' => $entry->rosterMembers->sortBy('display_order')->values()
                ->map(fn ($member): array => $this->participant($member->participant, $entry, $event))
                ->values(),
            'capabilities' => [
                'can_add_players' => ! $event->isArchived() && ! $locked && ! $published && ! $blocked,
                'can_edit_membership' => ! $event->isArchived() && ! $locked && ! $published && ! $blocked,
                'can_lock' => ! $event->isArchived() && ! $locked && ! $published && ! $blocked,
                'can_reopen' => ! $event->isArchived() && $locked && ! $published && ! $blocked,
                'published' => $published,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function participant(Participant $participant, ?Entry $entry, Event $event): array
    {
        $member = $entry?->rosterMembers->firstWhere('participant_id', $participant->getKey());
        $role = $member?->roleType();
        $isPlayer = $role !== null && in_array($role, self::PLAYER_ROLES, true);
        $locked = $entry?->isLocked() ?? false;
        $blocked = $entry !== null && in_array($entry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true);
        $published = $entry?->division?->tournaments?->contains(
            fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published,
        ) ?? false;
        $eventIsMutable = ! $event->isArchived();
        $rosterIsMutable = ! $locked && ! $published;
        $membershipIsActive = $member !== null && (bool) $member->is_active;
        return [
            'id' => (string) $participant->getKey(),
            'display_name' => $participant->display_name,
            'given_name' => $participant->given_name,
            'family_name' => $participant->family_name,
            'student_number' => $participant->student_number,
            'email' => $participant->email,
            'phone' => $participant->phone,
            'private_notes' => $participant->private_notes,
            'is_active' => (bool) $participant->is_active,
            'membership' => $member === null ? null : [
                'role' => $member->roleType()->value,
                'is_active' => (bool) $member->is_active,
                'notes' => $member->notes,
            ],
            'exception' => $entry === null ? null : $participant->participationExceptions->where('entry_id', $entry->getKey())->sortByDesc('recorded_at')->first()?->only(['type', 'reason', 'recorded_at']),
            'capabilities' => [
                'can_manage' => $eventIsMutable,
                'can_edit_profile' => $eventIsMutable,
                'can_edit_membership' => $eventIsMutable && $rosterIsMutable && ! $blocked,
                'can_restore_membership' => $eventIsMutable && $rosterIsMutable && ! $blocked && $member !== null && ! $membershipIsActive,
                'can_record_exception' => $eventIsMutable && ! $blocked && $isPlayer && $membershipIsActive && ($locked || $published),
            ],
        ];
    }
}
