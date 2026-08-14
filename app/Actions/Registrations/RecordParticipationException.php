<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\ParticipationExceptionType;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\ParticipationException;
use App\Models\RosterMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordParticipationException
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Event $event, Entry $entry, Participant $participant, ParticipationExceptionType $type, ?string $reason): ParticipationException
    {
        $reason = trim((string) $reason);
        if ($reason === '') throw ValidationException::withMessages(['reason' => 'A reason is required for a withdrawal or disqualification.']);
        if (! $actor->hasAdminAccess($event) || $event->isArchived() || $entry->eventId() !== (int) $event->getKey() || (int) $participant->event_id !== (int) $event->getKey() || (int) $participant->event_delegation_id !== (int) $entry->event_delegation_id) {
            throw new AuthorizationException('The selected roster records must belong to this mutable Event and Department.');
        }

        return DB::transaction(function () use ($actor, $event, $entry, $participant, $type, $reason): ParticipationException {
            $member = RosterMember::query()->where('entry_id', $entry->getKey())->where('participant_id', $participant->getKey())->lockForUpdate()->first();
            if (! $member) throw ValidationException::withMessages(['participant' => 'This person is not part of the selected roster.']);
            $exception = ParticipationException::create(['event_id' => $event->getKey(), 'entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'type' => $type, 'reason' => $reason, 'recorded_by' => $actor->getKey(), 'recorded_at' => now()]);
            $member->update(['is_active' => false]);
            $this->audit->record($actor, AuditAction::ParticipationExceptionRecorded, $exception, $event, after: ['entry_id' => (string) $entry->getKey(), 'participant_id' => (string) $participant->getKey(), 'type' => $type->value], reason: $reason);
            return $exception;
        });
    }
}
