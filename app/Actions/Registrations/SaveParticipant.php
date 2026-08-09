<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveParticipant
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Event $event, array $attributes, ?Participant $participant = null): Participant
    {
        $this->authorize($actor, $event, $participant);

        return DB::transaction(function () use ($actor, $event, $attributes, $participant): Participant {
            Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $delegation = EventDelegation::query()->whereKey($attributes['event_delegation_id'])->lockForUpdate()->firstOrFail();

            if ((int) $delegation->event_id !== (int) $event->getKey()) {
                throw new AuthorizationException('The selected Delegation does not belong to this Event.');
            }

            $record = $participant === null
                ? new Participant(['event_id' => $event->getKey(), 'created_by' => $actor->getKey()])
                : Participant::query()->whereKey($participant->getKey())->lockForUpdate()->firstOrFail();
            $before = $record->exists ? $this->auditData($record) : [];
            $newDelegationId = (int) $attributes['event_delegation_id'];

            if ($record->exists
                && (int) $record->event_delegation_id !== $newDelegationId
                && $record->rosterMembers()->exists()) {
                throw ValidationException::withMessages([
                    'event_delegation_id' => 'A Participant with roster history cannot be moved. Deactivate the old profile and create the corrected Delegation record.',
                ]);
            }

            if ($record->exists
                && ! (bool) ($attributes['is_active'] ?? true)
                && $record->rosterMembers()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'Deactivate each current roster membership or record an adverse eligibility decision before deactivating this Participant.',
                ]);
            }

            $studentNumber = $this->nullableString($attributes['student_number'] ?? null);
            $normalized = $studentNumber === null ? null : mb_strtoupper(trim($studentNumber));

            if ($normalized !== null && Participant::query()
                ->where('event_id', $event->getKey())
                ->where('student_number_normalized', $normalized)
                ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists()) {
                throw ValidationException::withMessages([
                    'student_number' => 'This student number is already registered in the selected Event.',
                ]);
            }

            $record->fill([
                ...Arr::only($attributes, [
                    'event_delegation_id', 'display_name', 'given_name', 'family_name',
                    'email', 'phone', 'private_notes', 'is_active',
                ]),
                'display_name' => trim((string) $attributes['display_name']),
                'given_name' => $this->nullableString($attributes['given_name'] ?? null),
                'family_name' => $this->nullableString($attributes['family_name'] ?? null),
                'student_number' => $studentNumber,
                'student_number_normalized' => $normalized,
                'email' => $this->nullableString($attributes['email'] ?? null),
                'phone' => $this->nullableString($attributes['phone'] ?? null),
                'private_notes' => $this->nullableString($attributes['private_notes'] ?? null),
            ]);
            $record->save();

            $this->audit->record(
                $actor,
                $participant === null ? AuditAction::ParticipantCreated : AuditAction::ParticipantUpdated,
                $record,
                $event,
                before: $before,
                after: $this->auditData($record),
            );

            return $record->fresh(['delegation']);
        });
    }

    private function authorize(User $actor, Event $event, ?Participant $participant): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        if ($participant !== null && (int) $participant->event_id !== (int) $event->getKey()) {
            throw new AuthorizationException('The selected Participant does not belong to this Event.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function auditData(Participant $participant): array
    {
        return [
            'event_delegation_id' => (string) $participant->event_delegation_id,
            'display_name' => $participant->display_name,
            'student_number_present' => $participant->student_number !== null,
            'email_present' => $participant->email !== null,
            'phone_present' => $participant->phone !== null,
            'is_active' => (bool) $participant->is_active,
        ];
    }
}
