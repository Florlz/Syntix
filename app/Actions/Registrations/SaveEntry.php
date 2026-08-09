<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\EntryStatus;
use App\Enums\TournamentState;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveEntry
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, Event $event, array $attributes, ?Entry $entry = null): Entry
    {
        $this->authorize($actor, $event, $entry);

        return DB::transaction(function () use ($actor, $event, $attributes, $entry): Entry {
            $division = Division::query()->with(['competition', 'governingRuleVersion'])->whereKey($attributes['competition_division_id'])->lockForUpdate()->firstOrFail();
            $delegation = EventDelegation::query()->whereKey($attributes['event_delegation_id'])->lockForUpdate()->firstOrFail();

            if ($division->eventId() !== (int) $event->getKey() || (int) $delegation->event_id !== (int) $event->getKey()) {
                throw new AuthorizationException('The selected Division or Delegation does not belong to this Event.');
            }

            $record = $entry === null
                ? new Entry(['status' => EntryStatus::Draft])
                : Entry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($record->exists && ($record->isLocked() || $this->isPublished($record))) {
                throw ValidationException::withMessages(['entry' => 'Locked or published Entries cannot be edited directly.']);
            }

            if ($record->exists
                && ((int) $record->competition_division_id !== (int) $division->getKey()
                    || (int) $record->event_delegation_id !== (int) $delegation->getKey())
                && $record->rosterMembers()->exists()) {
                throw ValidationException::withMessages([
                    'entry' => 'An Entry with roster history cannot move to another Division or Delegation. Create a corrected Entry instead.',
                ]);
            }

            $mode = $division->governingRuleVersion?->participantMode();

            if ($mode !== null && $mode->value !== (string) $attributes['entry_mode']) {
                throw ValidationException::withMessages(['entry_mode' => 'The Entry mode must match the governing Division rule.']);
            }

            $duplicate = Entry::query()
                ->where('competition_division_id', $division->getKey())
                ->where('event_delegation_id', $delegation->getKey())
                ->whereIn('status', [EntryStatus::Draft->value, EntryStatus::Active->value, EntryStatus::Locked->value])
                ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['entry' => 'This Delegation already has a current Entry in the selected Division.']);
            }

            $before = $record->exists ? $this->auditData($record) : [];
            $record->fill([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'code' => $this->nullableString($attributes['code'] ?? null),
                'name' => trim((string) $attributes['name']),
                'entry_mode' => $attributes['entry_mode'],
            ]);
            $record->save();
            $this->audit->record(
                $actor,
                $entry === null ? AuditAction::EntryCreated : AuditAction::EntryUpdated,
                $record,
                $event,
                before: $before,
                after: $this->auditData($record),
            );

            return $record->fresh(['division.competition', 'delegation']);
        });
    }

    private function authorize(User $actor, Event $event, ?Entry $entry): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        if ($entry !== null && $entry->eventId() !== (int) $event->getKey()) {
            throw new AuthorizationException('The selected Entry does not belong to this Event.');
        }
    }

    private function isPublished(Entry $entry): bool
    {
        return $entry->division()->whereHas('tournaments', fn ($query) => $query
            ->where('state', TournamentState::Published->value))->exists();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function auditData(Entry $entry): array
    {
        return [
            'competition_division_id' => (string) $entry->competition_division_id,
            'event_delegation_id' => (string) $entry->event_delegation_id,
            'name' => $entry->name,
            'entry_mode' => $entry->entryMode()->value,
            'status' => $entry->entryStatus()->value,
        ];
    }
}
