<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentState;
use App\Models\CompetitionRuleVersion;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveRosterMembership
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        RosterMemberRole $role,
        bool $active = true,
        ?string $notes = null,
        bool $allowAdverseRestore = false,
    ): RosterMember {
        $this->authorize($actor, $event, $entry, $participant);

        return DB::transaction(function () use ($actor, $event, $entry, $participant, $role, $active, $notes, $allowAdverseRestore): RosterMember {
            $lockedEntry = Entry::query()->with(['division.competition', 'division.governingRuleVersion'])
                ->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $lockedParticipant = Participant::query()->whereKey($participant->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedEntry->isLocked()) {
                throw ValidationException::withMessages(['entry' => 'Unlock this Entry before editing its roster.']);
            }

            if (in_array($lockedEntry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)) {
                throw ValidationException::withMessages(['entry' => 'A withdrawn or disqualified Entry cannot accept roster edits.']);
            }

            if ($this->hasPublishedTournament($lockedEntry)) {
                throw ValidationException::withMessages(['entry' => 'Published tournament rosters cannot be edited directly. Record an eligibility withdrawal or disqualification instead.']);
            }

            if ((int) $lockedParticipant->event_id !== (int) $event->getKey()
                || (int) $lockedParticipant->event_delegation_id !== (int) $lockedEntry->event_delegation_id) {
                throw new AuthorizationException('The Participant and Entry must belong to the same Event Delegation.');
            }

            if ($active && ! $lockedParticipant->is_active) {
                throw ValidationException::withMessages(['participant' => 'Inactive Participants cannot be added to a roster.']);
            }

            $rule = $lockedEntry->division->governingRuleVersion
                ?? $lockedEntry->division->ruleVersions()->latest('version')->first();

            if ($rule === null) {
                throw ValidationException::withMessages(['entry' => 'The selected Division has no roster rule.']);
            }

            $membership = RosterMember::query()
                ->where('entry_id', $lockedEntry->getKey())
                ->where('participant_id', $lockedParticipant->getKey())
                ->lockForUpdate()
                ->first();

            if ($active && $membership !== null && ! (bool) $membership->getAttribute('is_active') && ! $allowAdverseRestore) {
                $status = DB::table('eligibility_records')
                    ->where('entry_id', $lockedEntry->getKey())
                    ->where('participant_id', $lockedParticipant->getKey())
                    ->value('status');
                if (in_array($status, [
                    EligibilityStatus::Ineligible->value,
                    EligibilityStatus::Withdrawn->value,
                    EligibilityStatus::Disqualified->value,
                ], true)) {
                    throw ValidationException::withMessages([
                        'eligibility.status' => 'Choose eligible or pending when restoring an adverse roster history.',
                    ]);
                }
            }

            if ($active) {
                $this->assertLimits($lockedEntry, $lockedParticipant, $rule, $role, $membership);
            }

            $before = $membership === null ? [] : $this->auditData($membership);
            $membership ??= new RosterMember([
                'entry_id' => $lockedEntry->getKey(),
                'participant_id' => $lockedParticipant->getKey(),
            ]);
            $membership->fill([
                'role' => $role,
                'is_active' => $active,
                'notes' => $this->nullableString($notes),
                'display_order' => $membership->exists
                    ? $membership->display_order
                    : ((int) $lockedEntry->rosterMembers()->max('display_order')) + 1,
            ]);
            $membership->save();

            $this->audit->record(
                $actor,
                AuditAction::RosterMembershipSaved,
                $membership,
                $event,
                before: $before,
                after: $this->auditData($membership),
                context: ['preview_requires_redraw' => $this->hasPreviewTournament($lockedEntry)],
            );

            return $membership->fresh(['participant', 'entry']);
        });
    }

    private function authorize(User $actor, Event $event, Entry $entry, Participant $participant): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        if ($entry->eventId() !== (int) $event->getKey() || (int) $participant->event_id !== (int) $event->getKey()) {
            throw new AuthorizationException('The selected registration records do not belong to this Event.');
        }
    }

    private function assertLimits(
        Entry $entry,
        Participant $participant,
        CompetitionRuleVersion $rule,
        RosterMemberRole $role,
        ?RosterMember $membership,
    ): void {
        $activeMembers = $entry->rosterMembers()->where('is_active', true)
            ->when($membership !== null, fn ($query) => $query->whereKeyNot($membership->getKey()));
        $athleteRoles = [RosterMemberRole::StudentAthlete->value, RosterMemberRole::Reserve->value];

        if (in_array($role->value, $athleteRoles, true)
            && $rule->max_roster_size !== null
            && (clone $activeMembers)->whereIn('role', $athleteRoles)->count() >= (int) $rule->max_roster_size) {
            throw ValidationException::withMessages([
                'role' => "This Entry has reached its {$rule->max_roster_size}-athlete roster limit.",
            ]);
        }

        $roleLimit = $rule->roster_role_limits[$role->value] ?? null;

        if ($roleLimit !== null
            && (clone $activeMembers)->where('role', $role->value)->count() >= (int) $roleLimit) {
            throw ValidationException::withMessages([
                'role' => 'This Entry has reached the configured '.str_replace('_', ' ', $role->value).' limit.',
            ]);
        }

        if ($rule->participant_competition_limit === null) {
            return;
        }

        $competitionIds = RosterMember::query()
            ->where('participant_id', $participant->getKey())
            ->where('is_active', true)
            ->when($membership !== null, fn ($query) => $query->whereKeyNot($membership->getKey()))
            ->whereHas('entry.division.competition', fn ($query) => $query->where('event_id', $participant->event_id))
            ->with('entry.division:id,competition_id')
            ->get()
            ->pluck('entry.division.competition_id')
            ->push($entry->division->competition_id)
            ->unique();

        if ($competitionIds->count() > (int) $rule->participant_competition_limit) {
            throw ValidationException::withMessages([
                'participant' => "This Participant has reached the {$rule->participant_competition_limit}-competition limit.",
            ]);
        }
    }

    private function hasPublishedTournament(Entry $entry): bool
    {
        return $entry->division->tournaments()->where('state', TournamentState::Published->value)->exists();
    }

    private function hasPreviewTournament(Entry $entry): bool
    {
        return $entry->division->tournaments()->whereIn('state', [
            TournamentState::Preview->value,
            TournamentState::Uncontested->value,
        ])->exists();
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function auditData(RosterMember $membership): array
    {
        return [
            'entry_id' => (string) $membership->entry_id,
            'participant_id' => (string) $membership->participant_id,
            'role' => $membership->roleType()->value,
            'is_active' => (bool) $membership->is_active,
        ];
    }
}
