<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\EligibilityStatus;
use App\Enums\TournamentState;
use App\Models\EligibilityRecord;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetEligibility
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        User $actor,
        Event $event,
        Entry $entry,
        Participant $participant,
        EligibilityStatus $status,
        ?string $reason = null,
    ): EligibilityRecord {
        $this->authorize($actor, $event, $entry, $participant);
        $reason = $this->nullableString($reason);
        $adverse = in_array($status, [EligibilityStatus::Ineligible, EligibilityStatus::Withdrawn, EligibilityStatus::Disqualified], true);

        if ($adverse && $reason === null) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for an adverse eligibility decision.']);
        }

        return DB::transaction(function () use ($actor, $event, $entry, $participant, $status, $reason, $adverse): EligibilityRecord {
            $lockedEntry = Entry::query()->with('division.tournaments')->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $membership = RosterMember::query()
                ->where('entry_id', $lockedEntry->getKey())
                ->where('participant_id', $participant->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages(['participant' => 'Add this Participant to the Entry before recording eligibility.']);
            }

            $published = $lockedEntry->division->tournaments->contains(
                fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published,
            );

            if (($lockedEntry->isLocked() || $published) && ! $adverse) {
                throw ValidationException::withMessages([
                    'status' => 'Approved or published team sheets only allow ineligible, withdrawal, or disqualification corrections.',
                ]);
            }

            if (! $adverse && ! $membership->is_active) {
                throw ValidationException::withMessages(['participant' => 'Reactivate the roster membership before restoring eligibility.']);
            }

            $record = EligibilityRecord::query()
                ->where('entry_id', $lockedEntry->getKey())
                ->where('participant_id', $participant->getKey())
                ->lockForUpdate()
                ->first() ?? new EligibilityRecord([
                    'event_id' => $event->getKey(),
                    'entry_id' => $lockedEntry->getKey(),
                    'participant_id' => $participant->getKey(),
                ]);
            $before = $record->exists ? $this->auditData($record) : [];
            $record->fill([
                'status' => $status,
                'reason' => $reason,
                'checked_by' => $actor->getKey(),
                'checked_at' => now(),
            ]);
            $record->save();

            if ($adverse && $membership->is_active) {
                $membership->update(['is_active' => false]);
            }

            $this->audit->record(
                $actor,
                AuditAction::EligibilitySet,
                $record,
                $event,
                before: $before,
                after: $this->auditData($record),
                reason: $reason,
                context: [
                    'entry_locked' => $lockedEntry->isLocked(),
                    'tournament_published' => $published,
                    'membership_deactivated' => $adverse,
                ],
            );

            return $record->fresh(['checkedBy']);
        });
    }

    private function authorize(User $actor, Event $event, Entry $entry, Participant $participant): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        if ($entry->eventId() !== (int) $event->getKey()
            || (int) $participant->event_id !== (int) $event->getKey()
            || (int) $entry->event_delegation_id !== (int) $participant->event_delegation_id) {
            throw new AuthorizationException('The selected registration records do not share this Event Delegation.');
        }
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function auditData(EligibilityRecord $record): array
    {
        return [
            'entry_id' => (string) $record->entry_id,
            'participant_id' => (string) $record->participant_id,
            'status' => $record->eligibilityStatus()->value,
            'checked_by' => $record->checked_by === null ? null : (string) $record->checked_by,
            'checked_at' => $record->checked_at?->toIso8601String(),
        ];
    }
}
