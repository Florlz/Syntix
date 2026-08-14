<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\DisciplineFamily;
use App\Enums\DisciplineEntryState;
use App\Enums\EntryStatus;
use App\Models\Discipline;
use App\Models\DisciplineEntry;
use App\Models\Entry;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SaveDisciplineEntry
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<array{participant_id:int|string,is_starter?:bool,is_active?:bool,notes?:string|null}>  $members
     */
    public function handle(
        User $actor,
        Event $event,
        Discipline $discipline,
        Entry $entry,
        array $members,
        string $state = 'draft',
    ): DisciplineEntry {
        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can manage discipline Entries.');

        return DB::transaction(function () use ($actor, $event, $discipline, $entry, $members, $state): DisciplineEntry {
            $discipline = Discipline::query()
                ->with('division.competition')
                ->whereKey($discipline->getKey())
                ->firstOrFail();
            $entry = Entry::query()
                ->with(['division.competition', 'rosterMembers.participant'])
                ->whereKey($entry->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $discipline->division?->competition?->event_id !== (int) $event->getKey()
                || (int) $entry->division?->competition?->event_id !== (int) $event->getKey()
                || (int) $discipline->competition_division_id !== (int) $entry->competition_division_id) {
                throw new AuthorizationException('The discipline and Entry must belong to the selected event and Division.');
            }

            try {
                $entryState = DisciplineEntryState::from(strtolower($state));
            } catch (\ValueError) {
                throw new \InvalidArgumentException('A discipline Entry can only be saved as draft or locked.');
            }

            $existing = DisciplineEntry::query()
                ->where('discipline_id', $discipline->getKey())
                ->where('entry_id', $entry->getKey())
                ->lockForUpdate()
                ->first();
            if ($existing?->isLocked()) {
                throw new \DomainException('A locked discipline Entry is immutable.');
            }

            $normalized = [];
            foreach ($members as $member) {
                $participantId = (int) ($member['participant_id'] ?? 0);
                if ($participantId < 1 || isset($normalized[$participantId])) {
                    throw new \InvalidArgumentException('Discipline participants must be unique roster members.');
                }
                $normalized[$participantId] = [
                    'participant_id' => $participantId,
                    'is_starter' => (bool) ($member['is_starter'] ?? false),
                    'is_active' => array_key_exists('is_active', $member) ? (bool) $member['is_active'] : true,
                    'notes' => $member['notes'] ?? null,
                ];
            }

            $roster = $entry->rosterMembers
                ->filter(fn ($member) => $member->is_active && $member->participant?->is_active)
                ->keyBy(fn ($member): int => (int) $member->participant_id);
            foreach ($normalized as $member) {
                if (! $roster->has($member['participant_id'])) {
                    throw new \InvalidArgumentException('Every discipline participant must be an active member of the parent Entry roster.');
                }
            }

            if ($entryState === DisciplineEntryState::Locked) {
                if ($entry->entryStatus() !== EntryStatus::Locked) {
                    throw new \DomainException('Lock the parent Entry before locking a discipline Entry.');
                }

                $starterCount = collect($normalized)->where('is_starter', true)->count();
                $requiredStarters = (int) data_get($discipline->metadata, 'starter_count', 1);
                if ($discipline->familyType() === DisciplineFamily::Combat && $starterCount !== 1) {
                    throw new \DomainException('Combat disciplines require exactly one starter per department.');
                }
                if ($starterCount !== $requiredStarters) {
                    throw new \DomainException("This discipline requires exactly {$requiredStarters} starter.");
                }

                $reserveLimit = (int) data_get($discipline->metadata, 'reserve_count', 2);
                if (count($normalized) > $requiredStarters + $reserveLimit) {
                    throw new \DomainException("This discipline allows {$requiredStarters} starter(s) and up to {$reserveLimit} reserve(s).");
                }
            }

            $before = $existing?->only(['state', 'locked_at', 'locked_by']) ?? [];
            $disciplineEntry = $existing ?? new DisciplineEntry;
            $disciplineEntry->fill([
                'discipline_id' => $discipline->getKey(),
                'entry_id' => $entry->getKey(),
                'event_delegation_id' => $entry->event_delegation_id,
                'state' => $entryState,
                'locked_at' => $entryState === DisciplineEntryState::Locked ? now() : null,
                'locked_by' => $entryState === DisciplineEntryState::Locked ? $actor->getKey() : null,
                'status_reason' => null,
            ]);
            $disciplineEntry->save();

            $disciplineEntry->members()->delete();
            if ($normalized !== []) {
                $disciplineEntry->members()->createMany(array_values($normalized));
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                $entryState === DisciplineEntryState::Locked ? AuditAction::DisciplineEntryStateChanged : AuditAction::DisciplineEntrySaved,
                $disciplineEntry,
                $event,
                before: $before,
                after: ['state' => $entryState->value, 'participant_ids' => array_keys($normalized)],
            );

            return $disciplineEntry->fresh(['members.participant', 'discipline', 'entry']);
        });
    }
}
