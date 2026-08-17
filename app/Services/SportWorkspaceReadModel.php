<?php

namespace App\Services;

use App\Enums\DivisionPlacementState;
use App\Enums\EntryStatus;
use App\Enums\ResultSubmissionState;
use App\Enums\ScheduleStatus;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\Division;

final class SportWorkspaceReadModel
{
    /** @return array{sport: array<string, mixed>, divisions: list<array<string, mixed>>} */
    public function forSport(Competition $sport): array
    {
        $this->loadSport($sport);

        return [
            'sport' => $this->sport($sport),
            'divisions' => $this->divisions($sport),
        ];
    }

    /** @return array<string, mixed> */
    public function sport(Competition $sport): array
    {
        $this->loadSport($sport);
        $entries = $sport->divisions->flatMap->entries;

        return [
            'id' => (string) $sport->getKey(),
            'name' => $sport->name,
            'slug' => $sport->slug,
            'active' => (bool) $sport->is_active,
            'division_count' => $sport->divisions->where('is_active', true)->count(),
            'entry_count' => $entries->count(),
            'player_count' => $entries->flatMap->rosterMembers
                ->where('is_active', true)
                ->pluck('participant_id')
                ->unique()
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function division(Division $division): array
    {
        $this->loadDivision($division);
        $rule = $division->governingRuleVersion ?? $division->ruleVersions->sortByDesc('version')->first();
        $entries = $division->entries;
        $participatingEntries = $entries->filter(fn ($entry): bool => ! in_array($entry->entryStatus(), [
            EntryStatus::Withdrawn,
            EntryStatus::Disqualified,
        ], true));
        $schedules = $division->schedules;
        $scheduleState = match (true) {
            $schedules->isEmpty() => 'not_scheduled',
            $schedules->contains(fn ($schedule): bool => $schedule->currentPublication === null || $schedule->hasUnpublishedChanges()) => 'draft',
            default => 'published',
        };
        $nextSchedule = $schedules
            ->filter(fn ($schedule): bool => ! in_array($schedule->status, [
                ScheduleStatus::Cancelled,
                ScheduleStatus::Completed,
            ], true))
            ->sortBy('starts_at')
            ->first(fn ($item): bool => $item->starts_at?->isFuture() ?? false);
        $tournament = $division->tournaments
            ->sortByDesc('id')
            ->first(fn ($item): bool => in_array($item->tournamentState(), [
                TournamentState::Preview,
                TournamentState::Published,
                TournamentState::Uncontested,
            ], true));

        return [
            'id' => (string) $division->getKey(),
            'name' => $division->name,
            'active' => (bool) $division->is_active,
            'entry_count' => $entries->count(),
            'participating_entry_count' => $participatingEntries->count(),
            'locked_entry_count' => $participatingEntries->filter(fn ($entry): bool => $entry->entryStatus() === EntryStatus::Locked)->count(),
            'unlocked_entry_count' => $participatingEntries->filter(fn ($entry): bool => $entry->entryStatus() !== EntryStatus::Locked)->count(),
            'player_count' => $entries->flatMap->rosterMembers
                ->where('is_active', true)
                ->pluck('participant_id')
                ->unique()
                ->count(),
            'format' => $rule?->format()?->value,
            'participant_mode' => $rule?->participantMode()?->value,
            'rule_state' => $rule?->lifecycleState()->value ?? 'missing',
            'blockers' => $rule?->readinessErrors() ?? ['No rule version is configured.'],
            'bracket_state' => $tournament?->tournamentState()->value ?? 'not_generated',
            'schedule_state' => $scheduleState,
            'results_state' => $this->resultsState($division),
            'next_schedule' => $nextSchedule === null ? null : [
                'title' => $nextSchedule->title,
                'starts_at' => $nextSchedule->starts_at?->toIso8601String(),
                'venue' => $nextSchedule->venue?->name,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function divisions(Competition $sport): array
    {
        $this->loadSport($sport);

        return $sport->divisions
            ->map(fn (Division $division): array => $this->division($division))
            ->values()
            ->all();
    }

    private function resultsState(Division $division): string
    {
        $submissions = $division->contests->flatMap->resultSubmissions;
        $placements = $division->placements;

        $hasPendingReview = $submissions->contains(
            fn ($submission): bool => $submission->submissionState() === ResultSubmissionState::Submitted,
        ) || $placements->contains(
            fn ($placement): bool => $placement->placementState() === DivisionPlacementState::Submitted,
        );

        if ($hasPendingReview) {
            return 'pending_review';
        }

        if ($placements->contains(
            fn ($placement): bool => $placement->placementState() === DivisionPlacementState::Approved,
        )) {
            return 'complete';
        }

        return $division->contests->isEmpty() && $submissions->isEmpty() && $placements->isEmpty()
            ? 'not_started'
            : 'in_progress';
    }

    private function loadSport(Competition $sport): void
    {
        $sport->loadMissing([
            'divisions.governingRuleVersion.criteria',
            'divisions.ruleVersions.criteria',
            'divisions.entries.rosterMembers',
            'divisions.contests.resultSubmissions',
            'divisions.placements',
            'divisions.tournaments',
            'divisions.schedules.currentPublication',
            'divisions.schedules.division.competition',
            'divisions.schedules.venue',
        ]);
    }

    private function loadDivision(Division $division): void
    {
        $division->loadMissing([
            'governingRuleVersion.criteria',
            'ruleVersions.criteria',
            'entries.rosterMembers',
            'contests.resultSubmissions',
            'placements',
            'tournaments',
            'schedules.currentPublication',
            'schedules.division.competition',
            'schedules.venue',
        ]);
    }
}
