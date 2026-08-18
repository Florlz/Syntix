<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Division;
use App\Models\Schedule;

final class ContestScheduleReadModel
{
    /** @return array{starts_at: ?string, ends_at: ?string, title: ?string, venue: ?array{id: string, name: string, location: ?string}} */
    public function for(Contest $contest): array
    {
        return $this->forContest($contest);
    }

    /** @return array{starts_at: ?string, ends_at: ?string, title: ?string, venue: ?array{id: string, name: string, location: ?string}} */
    public function forContest(Contest $contest): array
    {
        $contest->loadMissing('division.competition');

        return $this->dto($this->resolve(
            $contest->division?->competition?->event_id,
            $contest->competition_division_id,
            $contest->getKey(),
        ));
    }

    /** @return array{starts_at: ?string, ends_at: ?string, title: ?string, venue: ?array{id: string, name: string, location: ?string}} */
    public function forDivision(Division $division): array
    {
        $division->loadMissing('competition');

        return $this->dto($this->resolve(
            $division->competition?->event_id,
            $division->getKey(),
            null,
        ));
    }

    public function findForContest(Contest $contest): ?Schedule
    {
        $contest->loadMissing('division.competition');

        return $this->resolve(
            $contest->division?->competition?->event_id,
            $contest->competition_division_id,
            $contest->getKey(),
        );
    }

    private function resolve(?int $eventId, ?int $divisionId, ?int $contestId): ?Schedule
    {
        if ($eventId === null || $divisionId === null) {
            return null;
        }

        $query = Schedule::query()
            ->with('venue')
            ->where('event_id', $eventId)
            ->where('competition_division_id', $divisionId)
            ->orderBy('starts_at');

        if ($contestId !== null) {
            $contestSchedule = (clone $query)
                ->where('contest_id', $contestId)
                ->first();

            if ($contestSchedule !== null) {
                return $contestSchedule;
            }
        }

        return $query->whereNull('contest_id')->first();
    }

    /** @return array{starts_at: ?string, ends_at: ?string, title: ?string, venue: ?array{id: string, name: string, location: ?string}} */
    private function dto(?Schedule $schedule): array
    {
        return [
            'starts_at' => $schedule?->starts_at?->toIso8601String(),
            'ends_at' => $schedule?->ends_at?->toIso8601String(),
            'title' => $schedule?->title,
            'venue' => $schedule?->venue === null ? null : [
                'id' => (string) $schedule->venue->getKey(),
                'name' => $schedule->venue->name,
                'location' => $schedule->venue->location,
            ],
        ];
    }
}
