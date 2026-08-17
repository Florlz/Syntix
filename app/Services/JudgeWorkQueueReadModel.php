<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\ScorecardState;
use App\Enums\ScoringAssignmentScope;
use App\Models\Contest;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\ScoringAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

final class JudgeWorkQueueReadModel
{
    /**
     * @return array{event: ?array<string, mixed>, summary: array<string, int>, contests: list<array<string, mixed>>}
     */
    public function for(User $user, ?int $eventId = null): array
    {
        $eventId ??= $this->currentEventId($user);

        if ($eventId === null) {
            return [
                'event' => null,
                'summary' => ['assigned' => 0, 'submitted' => 0, 'needs_correction' => 0, 'blocked' => 0],
                'contests' => [],
            ];
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return [
                'event' => null,
                'summary' => ['assigned' => 0, 'submitted' => 0, 'needs_correction' => 0, 'blocked' => 0],
                'contests' => [],
            ];
        }

        $scorecards = ScoringAssignment::query()
            ->active()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->where('scope_type', ScoringAssignmentScope::EntryScorecard->value)
            ->with([
                'entryScorecard.entry.delegation',
                'entryScorecard.contest.division.competition',
                'entryScorecard.contest.ruleVersion',
            ])
            ->get()
            ->map(fn (ScoringAssignment $assignment): ?EntryScorecard => $assignment->entryScorecard)
            ->filter()
            ->unique(fn (EntryScorecard $scorecard): int => (int) $scorecard->getKey())
            ->sortBy([
                ['contest.name', 'asc'],
                ['entry.name', 'asc'],
            ])
            ->values();

        $contests = $scorecards
            ->groupBy('contest_id')
            ->map(fn (Collection $items): array => $this->contestDto($items->first()->contest, $items))
            ->sortBy(fn (array $contest): string => strtolower($contest['name']))
            ->values()
            ->all();

        $allCards = collect($contests)->flatMap(fn (array $contest): array => $contest['scorecards']);

        return [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
            'summary' => [
                'assigned' => $allCards->count(),
                'submitted' => $allCards->whereIn('status', [ScorecardState::Submitted->value, ScorecardState::Approved->value])->count(),
                'needs_correction' => $allCards->where('status', 'needs_correction')->count(),
                'blocked' => $allCards->where('status', 'blocked')->count(),
            ],
            'contests' => $contests,
        ];
    }

    public function currentEventId(User $user): ?int
    {
        $eventId = $user->eventRoles()
            ->active()
            ->where('role', EventRole::Judge->value)
            ->latest('granted_at')
            ->value('event_id');

        return $eventId === null ? null : (int) $eventId;
    }

    /** @param Collection<int, EntryScorecard> $scorecards */
    private function contestDto(Contest $contest, Collection $scorecards): array
    {
        $contest->loadMissing('division.competition', 'ruleVersion');
        $rule = $contest->ruleVersion;
        $metadata = $rule?->metadata();
        $schedule = $this->scheduleFor($contest);
        $blocked = $metadata?->sourceBlocker !== null || $rule?->source_status === 'blocked' || ! $contest->isJudgingPanelLocked();
        $nextBlocker = $metadata?->sourceBlocker
            ?? ($rule?->source_status === 'blocked' ? 'The source rule is blocked.'
                : (! $contest->isJudgingPanelLocked() ? 'Waiting for the Global Admin to confirm and lock the judging panel.' : null));

        $cards = $scorecards->map(function (EntryScorecard $scorecard) use ($blocked): array {
            $state = $blocked ? 'blocked' : $this->statusFor($scorecard);

            return [
                'entry' => $scorecard->entry?->name ?? $scorecard->entry_reference ?? 'Assigned entry',
                'delegation' => $scorecard->entry?->delegation?->name,
                'status' => $state,
                'status_label' => $this->statusLabel($state),
                'href' => $blocked ? null : route('judge.scorecards.show', $scorecard),
            ];
        })->values()->all();

        $counts = collect(['not_started', 'in_progress', 'needs_correction', 'submitted', 'approved', 'blocked'])
            ->mapWithKeys(fn (string $status): array => [$status => collect($cards)->where('status', $status)->count()])
            ->all();

        return [
            'name' => $contest->name,
            'competition' => $contest->division?->competition?->name,
            'division' => $contest->division?->name,
            'scorecard_count' => count($cards),
            'entry_count' => count($cards),
            'counts' => $counts,
            'schedule' => $this->scheduleDto($schedule),
            'source' => $this->sourceDto($metadata),
            'readiness' => [
                'ready' => ! $blocked,
                'next_blocker' => $nextBlocker,
            ],
            'scorecards' => $cards,
        ];
    }

    private function statusFor(EntryScorecard $scorecard): string
    {
        return match ($scorecard->scorecardState()) {
            ScorecardState::Draft => ((int) $scorecard->revision > 0 || $scorecard->calculated_total !== null)
                ? 'in_progress'
                : 'not_started',
            ScorecardState::Rejected => 'needs_correction',
            ScorecardState::Submitted => 'submitted',
            ScorecardState::Approved => 'approved',
            ScorecardState::Completed => 'in_progress',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'not_started' => 'Not started',
            'in_progress' => 'In progress',
            'needs_correction' => 'Needs correction',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'blocked' => 'Blocked',
            default => 'Needs attention',
        };
    }

    private function scheduleFor(Contest $contest): ?Schedule
    {
        $schedule = Schedule::query()
            ->with('venue')
            ->where('event_id', $contest->division?->competition?->event_id)
            ->where('contest_id', $contest->getKey())
            ->orderBy('starts_at')
            ->first();

        return $schedule ?? Schedule::query()
            ->with('venue')
            ->where('event_id', $contest->division?->competition?->event_id)
            ->where('competition_division_id', $contest->competition_division_id)
            ->whereNull('contest_id')
            ->orderBy('starts_at')
            ->first();
    }

    /** @return array<string, mixed> */
    private function scheduleDto(?Schedule $schedule): array
    {
        return [
            'starts_at' => $schedule?->starts_at?->toIso8601String(),
            'ends_at' => $schedule?->ends_at?->toIso8601String(),
            'title' => $schedule?->title,
            'venue' => $schedule?->venue === null ? null : [
                'name' => $schedule->venue->name,
                'location' => $schedule->venue->location,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function sourceDto(?\App\Data\CompetitionRuleMetadata $metadata): array
    {
        return [
            'reliability' => $metadata?->reliabilityLabel,
            'pages' => $metadata?->sourcePages ?? [],
            'controls' => $metadata?->eventControls ?? [],
            'venue_candidates' => $metadata?->venueCandidates ?? [],
            'programme_day_hint' => $metadata?->programmeDayHint,
            'blocker' => $metadata?->sourceBlocker,
        ];
    }
}
