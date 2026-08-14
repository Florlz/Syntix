<?php

namespace App\Actions\Registrations;

use App\Enums\EligibilityStatus;
use App\Enums\RosterMemberRole;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the three parts of a sport-panel player edit as one operation.
 *
 * The event participant profile is shared by every sport, while membership
 * and eligibility belong to the selected Entry. Keeping this orchestration in
 * one transaction prevents a profile correction from leaving a half-updated
 * roster behind when a membership or eligibility rule rejects the request.
 */
final class SaveRosterPlayer
{
    public function __construct(
        private readonly SaveParticipant $saveParticipant,
        private readonly SaveRosterMembership $saveMembership,
        private readonly SetEligibility $setEligibility,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        array $payload,
    ): Participant {
        $this->authorize($actor, $event, $entry, $participant);

        return DB::transaction(function () use ($actor, $event, $entry, $participant, $payload): Participant {
            $lockedEntry = Entry::query()
                ->with(['division.competition', 'division.governingRuleVersion', 'division.tournaments'])
                ->whereKey($entry->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedParticipant = Participant::query()->whereKey($participant->getKey())->lockForUpdate()->firstOrFail();

            $this->assertContainment($event, $lockedEntry, $lockedParticipant);

            $profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];
            $membership = is_array($payload['membership'] ?? null) ? $payload['membership'] : [];
            $eligibility = is_array($payload['eligibility'] ?? null) ? $payload['eligibility'] : [];

            if ($profile !== []) {
                $this->saveProfileIfChanged($actor, $event, $lockedEntry, $lockedParticipant, $profile);
                $lockedParticipant = Participant::query()->whereKey($lockedParticipant->getKey())->lockForUpdate()->firstOrFail();
            }

            $currentMembership = RosterMember::query()
                ->where('entry_id', $lockedEntry->getKey())
                ->where('participant_id', $lockedParticipant->getKey())
                ->lockForUpdate()
                ->first();
            $currentEligibility = $lockedEntry->eligibilityRecords()
                ->where('participant_id', $lockedParticipant->getKey())
                ->lockForUpdate()
                ->first();
            $requestedActive = $membership === []
                ? false
                : (array_key_exists('is_active', $membership)
                    ? (bool) $membership['is_active']
                    : (bool) ($currentMembership?->is_active ?? true));
            $restoringMembership = $membership !== []
                && $currentMembership !== null
                && ! $currentMembership->is_active
                && $requestedActive;

            if ($membership !== []) {
                $this->saveMembershipIfChanged(
                    $actor,
                    $event,
                    $lockedEntry,
                    $lockedParticipant,
                    $currentMembership,
                    $membership,
                    $restoringMembership
                        && in_array((string) ($eligibility['status'] ?? ''), [
                            EligibilityStatus::Eligible->value,
                            EligibilityStatus::Pending->value,
                        ], true),
                );
                $currentMembership = RosterMember::query()
                    ->where('entry_id', $lockedEntry->getKey())
                    ->where('participant_id', $lockedParticipant->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            $this->saveEligibilityIfChanged(
                $actor,
                $event,
                $lockedEntry,
                $lockedParticipant,
                $currentMembership,
                $currentEligibility,
                $eligibility,
                $restoringMembership,
            );

            return $lockedParticipant->fresh(['delegation', 'rosterMembers.entry.division.competition', 'eligibilityRecords']);
        });
    }

    /** @param array<string, mixed> $profile */
    private function saveProfileIfChanged(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        array $profile,
    ): void {
        $attributes = [
            'event_delegation_id' => (int) $entry->event_delegation_id,
            'display_name' => $profile['display_name'] ?? $participant->display_name,
            'given_name' => array_key_exists('given_name', $profile) ? $profile['given_name'] : $participant->given_name,
            'family_name' => array_key_exists('family_name', $profile) ? $profile['family_name'] : $participant->family_name,
            'student_number' => array_key_exists('student_number', $profile) ? $profile['student_number'] : $participant->student_number,
            'email' => array_key_exists('email', $profile) ? $profile['email'] : $participant->email,
            'phone' => array_key_exists('phone', $profile) ? $profile['phone'] : $participant->phone,
            'private_notes' => array_key_exists('private_notes', $profile) ? $profile['private_notes'] : $participant->private_notes,
            // Shared deactivation belongs to the event directory, never this
            // sport-scoped panel.
            'is_active' => (bool) $participant->is_active,
        ];

        $changed = [
            'display_name', 'given_name', 'family_name', 'student_number',
            'email', 'phone', 'private_notes', 'event_delegation_id',
        ];
        foreach ($changed as $field) {
            if ($this->normalise($attributes[$field] ?? null) !== $this->normalise($participant->{$field})) {
                $this->saveParticipant->handle($actor, $event, $attributes, $participant);
                return;
            }
        }
    }

    /** @param array<string, mixed> $membershipData */
    private function saveMembershipIfChanged(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        ?RosterMember $current,
        array $membershipData,
        bool $allowAdverseRestore,
    ): void {
        $role = RosterMemberRole::from((string) ($membershipData['role'] ?? $current?->roleType()?->value ?? RosterMemberRole::StudentAthlete->value));
        $active = array_key_exists('is_active', $membershipData)
            ? (bool) $membershipData['is_active']
            : (bool) ($current?->is_active ?? true);
        $notes = array_key_exists('notes', $membershipData) ? $membershipData['notes'] : $current?->notes;

        if ($current !== null
            && $current->roleType() === $role
            && (bool) $current->is_active === $active
            && $this->normalise($current->notes) === $this->normalise($notes)) {
            return;
        }

        $this->saveMembership->handle(
            $actor,
            $event,
            $entry,
            $participant,
            $role,
            $active,
            $notes,
            $allowAdverseRestore,
        );
    }

    /** @param array<string, mixed> $eligibilityData */
    private function saveEligibilityIfChanged(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        ?RosterMember $membership,
        mixed $current,
        array $eligibilityData,
        bool $restoringMembership,
    ): void {
        if ($eligibilityData === []) {
            if ($restoringMembership
                && $membership !== null
                && $current !== null
                && in_array($current->eligibilityStatus(), $this->adverseStatuses(), true)) {
                throw ValidationException::withMessages([
                    'eligibility.status' => 'Choose eligible or pending when restoring an adverse roster history.',
                ]);
            }
            return;
        }

        if (! array_key_exists('status', $eligibilityData)) {
            throw ValidationException::withMessages(['eligibility.status' => 'Choose an eligibility status.']);
        }

        $role = $membership?->roleType();
        if ($role === null || ! in_array($role, $this->playerRoles(), true)) {
            throw ValidationException::withMessages(['eligibility.status' => 'Eligibility decisions apply to players, not team staff.']);
        }

        $status = EligibilityStatus::from((string) $eligibilityData['status']);
        if ($restoringMembership && ! in_array($status, [EligibilityStatus::Eligible, EligibilityStatus::Pending], true)) {
            throw ValidationException::withMessages([
                'eligibility.status' => 'Choose eligible or pending when restoring an adverse roster history.',
            ]);
        }
        $reason = $eligibilityData['reason'] ?? null;
        if ($current !== null && $current->eligibilityStatus() === $status && $this->normalise($current->reason) === $this->normalise($reason)) {
            return;
        }

        $this->setEligibility->handle($actor, $event, $entry, $participant, $status, $reason);
    }

    private function authorize(User $actor, Event $event, Entry $entry, Participant $participant): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        $this->assertContainment($event, $entry, $participant);
    }

    private function assertContainment(Event $event, Entry $entry, Participant $participant): void
    {
        if ($entry->eventId() !== (int) $event->getKey()
            || (int) $participant->event_id !== (int) $event->getKey()
            || (int) $entry->event_delegation_id !== (int) $participant->event_delegation_id) {
            throw new AuthorizationException('The selected Participant and Entry must share this Event Delegation.');
        }
    }

    /** @return list<RosterMemberRole> */
    private function playerRoles(): array
    {
        return [RosterMemberRole::StudentAthlete, RosterMemberRole::Reserve];
    }

    /** @return list<EligibilityStatus> */
    private function adverseStatuses(): array
    {
        return [EligibilityStatus::Ineligible, EligibilityStatus::Withdrawn, EligibilityStatus::Disqualified];
    }

    private function normalise(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
