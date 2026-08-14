<?php

namespace App\Services;

use App\Enums\CoachAssignmentScope;
use App\Models\CoachAssignment;
use App\Models\Entry;
use Illuminate\Support\Collection;

final class CoachAssignmentResolver
{
    /** @return Collection<int, CoachAssignment> */
    public function forEntry(Entry $entry): Collection
    {
        $entry->loadMissing('division.competition');
        $family = $entry->division->competition->programme_family;

        return CoachAssignment::query()
            ->where('event_id', $entry->eventId())
            ->where('event_delegation_id', $entry->event_delegation_id)
            ->where('is_active', true)
            ->where(function ($query) use ($entry, $family): void {
                $query->where(fn ($query) => $query->where('scope_type', CoachAssignmentScope::Division->value)->where('scope_key', (string) $entry->competition_division_id));
                if ($family) $query->orWhere(fn ($query) => $query->where('scope_type', CoachAssignmentScope::ProgrammeFamily->value)->where('scope_key', $family));
            })
            ->with('participant')
            ->orderBy('coach_type')->orderBy('title')->get();
    }
}
