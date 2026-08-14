<?php

namespace App\Actions\Registrations;

use App\Enums\RosterMemberRole;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveRosterMembershipBatch
{
    public function __construct(private readonly SaveRosterMembership $saveMembership) {}

    /** @param array<int, array{participant_id: int, role: RosterMemberRole|string}> $members */
    public function handle(User $actor, Event $event, Entry $entry, array $members): Collection
    {
        if ($members === [] || count($members) > 100) {
            throw ValidationException::withMessages(['members' => 'Select between 1 and 100 people.']);
        }

        $ids = array_map(static fn (array $member): int => (int) $member['participant_id'], $members);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['members' => 'A person can only be selected once.']);
        }

        return DB::transaction(function () use ($actor, $event, $entry, $members, $ids): Collection {
            $lockedEntry = Entry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedEntry->eventId() !== (int) $event->getKey()) {
                throw ValidationException::withMessages(['members' => 'This roster is no longer available in the selected Event. Refresh and try again.']);
            }

            $participants = Participant::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($participants->count() !== count($ids)) {
                throw ValidationException::withMessages(['members' => 'One or more selected people no longer exist. Refresh the roster and try again.']);
            }

            if ($participants->contains(fn (Participant $participant): bool => (int) $participant->event_id !== (int) $event->getKey()
                || (int) $participant->event_delegation_id !== (int) $lockedEntry->event_delegation_id)) {
                throw ValidationException::withMessages(['members' => 'One or more selected people are no longer available for this department roster. Refresh and try again.']);
            }

            return collect($members)->map(function (array $member) use ($actor, $event, $lockedEntry, $participants): RosterMember {
                $participant = $participants->get((int) $member['participant_id']);
                return $this->saveMembership->handle(
                    $actor,
                    $event,
                    $lockedEntry,
                    $participant,
                    $member['role'] instanceof RosterMemberRole ? $member['role'] : RosterMemberRole::from((string) $member['role']),
                    true,
                );
            });
        });
    }
}
