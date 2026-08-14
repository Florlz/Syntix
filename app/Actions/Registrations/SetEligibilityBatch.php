<?php

namespace App\Actions\Registrations;

use App\Enums\EligibilityStatus;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetEligibilityBatch
{
    public function __construct(private readonly SetEligibility $setEligibility) {}

    /** @param array<int, int> $participantIds */
    public function handle(User $actor, Event $event, Entry $entry, array $participantIds, EligibilityStatus $status, ?string $reason = null): Collection
    {
        if ($participantIds === [] || count($participantIds) > 100) {
            throw ValidationException::withMessages(['participant_ids' => 'Select between 1 and 100 people.']);
        }
        if (count($participantIds) !== count(array_unique($participantIds))) {
            throw ValidationException::withMessages(['participant_ids' => 'A person can only be selected once.']);
        }

        return DB::transaction(function () use ($actor, $event, $entry, $participantIds, $status, $reason): Collection {
            $lockedEntry = Entry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $participants = Participant::query()->whereIn('id', $participantIds)->lockForUpdate()->get()->keyBy('id');
            if ($participants->count() !== count($participantIds)) {
                throw ValidationException::withMessages(['participant_ids' => 'One or more selected people no longer exist. Refresh the roster and try again.']);
            }

            return collect($participantIds)->map(fn (int $participantId) => $this->setEligibility->handle(
                $actor,
                $event,
                $lockedEntry,
                $participants->get($participantId),
                $status,
                $reason,
            ));
        });
    }
}
