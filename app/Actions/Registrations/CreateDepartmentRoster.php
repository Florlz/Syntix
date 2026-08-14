<?php

namespace App\Actions\Registrations;

use App\Enums\EntryStatus;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDepartmentRoster
{
    public function __construct(private readonly SaveEntry $saveEntry) {}

    public function handle(User $actor, Event $event, Division $division, EventDelegation $department): Entry
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived()) {
            throw new AuthorizationException('The active Global Admin is required and the Event must be mutable.');
        }

        return DB::transaction(function () use ($actor, $event, $division, $department): Entry {
            $lockedEvent = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $lockedDivision = Division::query()->with(['competition', 'governingRuleVersion'])->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            $lockedDepartment = EventDelegation::query()->whereKey($department->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedDivision->eventId() !== (int) $lockedEvent->getKey()
                || (int) $lockedDepartment->event_id !== (int) $lockedEvent->getKey()) {
                throw new AuthorizationException('The selected Division and Department must belong to this Event.');
            }

            $existing = Entry::query()
                ->where('competition_division_id', $lockedDivision->getKey())
                ->where('event_delegation_id', $lockedDepartment->getKey())
                ->whereIn('status', [EntryStatus::Draft->value, EntryStatus::Active->value, EntryStatus::Locked->value])
                ->latest('id')
                ->first();
            if ($existing !== null) {
                return $existing->fresh(['division.competition', 'delegation']);
            }

            $mode = $lockedDivision->governingRuleVersion?->participantMode();
            if ($mode === null) {
                throw ValidationException::withMessages(['entry' => 'Configure a roster rule for this Division before creating a department roster.']);
            }

            return $this->saveEntry->handle($actor, $lockedEvent, [
                'competition_division_id' => $lockedDivision->getKey(),
                'event_delegation_id' => $lockedDepartment->getKey(),
                'code' => $lockedDepartment->abbreviation,
                'name' => trim($lockedDepartment->abbreviation.' '.$lockedDivision->competition?->name.' '.$lockedDivision->name),
                'entry_mode' => $mode->value,
            ]);
        });
    }
}
