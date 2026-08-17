<?php

namespace App\Services;

use App\Data\CompetitionRuleMetadata;
use App\Enums\ContestState;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Enums\ScoringFamily;
use App\Models\Contest;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\ScoringAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

final class TabulatorWorkQueueReadModel
{
    /**
     * @return array{event: ?array<string, mixed>, summary: array<string, int>, judged: list<array<string, mixed>>, objective: list<array<string, mixed>>}
     */
    public function for(User $user, ?int $eventId = null): array
    {
        $eventId ??= $this->currentEventId($user);

        if ($eventId === null) {
            return ['event' => null, 'summary' => ['judged' => 0, 'objective' => 0], 'judged' => [], 'objective' => []];
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['event' => null, 'summary' => ['judged' => 0, 'objective' => 0], 'judged' => [], 'objective' => []];
        }

        $assignments = ScoringAssignment::query()
            ->active()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->whereIn('scope_type', [ScoringAssignmentScope::CompetitionDivision->value, ScoringAssignmentScope::Contest->value])
            ->with([
                'division.competition',
                'division.contests.entries',
                'division.contests.scorecards',
                'division.contests.ruleVersion',
                'contest.division.competition',
                'contest.entries',
                'contest.scorecards',
                'contest.ruleVersion',
            ])
            ->get();

        $contests = $assignments
            ->flatMap(fn (ScoringAssignment $assignment): Collection => $assignment->scopeType() === ScoringAssignmentScope::Contest
                ? collect([$assignment->contest])
                : $assignment->division?->contests ?? collect())
            ->filter(fn (?Contest $contest): bool => $contest !== null)
            ->unique(fn (Contest $contest): int => (int) $contest->getKey())
            ->sortBy(fn (Contest $contest): string => strtolower($contest->name))
            ->values();

        $items = $contests->map(fn (Contest $contest): array => $this->contestDto($contest))->values();

        return [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
            'summary' => [
                'judged' => $items->where('mode', 'judged')->count(),
                'objective' => $items->where('mode', 'objective')->count(),
            ],
            'judged' => $items->where('mode', 'judged')->values()->all(),
            'objective' => $items->where('mode', 'objective')->values()->all(),
        ];
    }

    public function currentEventId(User $user): ?int
    {
        $eventId = $user->eventRoles()
            ->active()
            ->where('role', EventRole::Tabulator->value)
            ->latest('granted_at')
            ->value('event_id');

        return $eventId === null ? null : (int) $eventId;
    }

    /** @return array<string, mixed> */
    private function contestDto(Contest $contest): array
    {
        $contest->loadMissing('division.competition', 'ruleVersion', 'entries', 'scorecards');
        $rule = $contest->ruleVersion;
        $metadata = $rule?->metadata();
        $mode = (($contest->live_payload ?? [])['scoring_mode'] ?? null) === 'judged'
            || $rule?->scoringFamily() === ScoringFamily::CriteriaBased
            ? 'judged'
            : 'objective';
        $schedule = $this->scheduleFor($contest);

        if ($mode === 'objective') {
            return [
                'mode' => $mode,
                'name' => $contest->name,
                'competition' => $contest->division?->competition?->name,
                'division' => $contest->division?->name,
                'state' => $contest->state instanceof ContestState ? $contest->state->value : (string) $contest->state,
                'state_label' => $this->objectiveStateLabel($contest),
                'href' => route('tabulator.contests.show', $contest),
                'schedule' => $this->scheduleDto($schedule),
                'source' => $this->sourceDto($metadata),
            ];
        }

        $scorecards = $contest->scorecards->filter(fn ($scorecard): bool => $scorecard->judge_id !== null);
        $judgeCount = $scorecards->pluck('judge_id')->unique()->count();
        $expected = $contest->isJudgingPanelLocked()
            ? $contest->entries->count() * $judgeCount
            : $scorecards->count();
        $submitted = $scorecards->whereIn('state', ['submitted', 'approved'])->count();
        $waiting = max(0, $expected - $submitted);
        $nextBlocker = $this->judgedBlocker($contest, $rule, $scorecards->count(), $waiting);

        return [
            'mode' => $mode,
            'name' => $contest->name,
            'competition' => $contest->division?->competition?->name,
            'division' => $contest->division?->name,
            'href' => route('tabulator.contests.show', $contest),
            'schedule' => $this->scheduleDto($schedule),
            'completion' => ['submitted' => $submitted, 'expected' => $expected, 'waiting' => $waiting],
            'readiness' => ['ready' => $nextBlocker === null, 'next_blocker' => $nextBlocker],
            'panel' => ['locked' => $contest->isJudgingPanelLocked(), 'judge_count' => $judgeCount],
            'source' => $this->sourceDto($metadata),
        ];
    }

    private function judgedBlocker(Contest $contest, $rule, int $scorecardCount, int $waiting): ?string
    {
        if ($rule?->source_status === 'blocked' || $rule?->metadata()->sourceBlocker !== null) {
            return $rule?->metadata()->sourceBlocker ?? 'The source rule is blocked.';
        }

        if ($scorecardCount === 0) {
            return 'Judging panel has not been configured.';
        }

        if (! $contest->isJudgingPanelLocked()) {
            return 'Judging panel must be locked before tabulation.';
        }

        if ($waiting > 0) {
            return 'Waiting for '.$waiting.' Judge scorecards.';
        }

        if ($rule === null || ! $rule->hasConfirmedAggregation()) {
            return 'Confirm Judge score aggregation method with an administrative reference and reason.';
        }

        return null;
    }

    private function objectiveStateLabel(Contest $contest): string
    {
        return match ($contest->state instanceof ContestState ? $contest->state : ContestState::tryFrom((string) $contest->state)) {
            ContestState::Completed => 'Complete',
            ContestState::Live => 'In progress',
            ContestState::Cancelled, ContestState::Suspended => 'Needs attention',
            default => 'Ready',
        };
    }

    private function scheduleFor(Contest $contest): ?Schedule
    {
        $eventId = $contest->division?->competition?->event_id;

        return Schedule::query()->with('venue')->where('event_id', $eventId)->where('contest_id', $contest->getKey())->orderBy('starts_at')->first()
            ?? Schedule::query()->with('venue')->where('event_id', $eventId)->where('competition_division_id', $contest->competition_division_id)->whereNull('contest_id')->orderBy('starts_at')->first();
    }

    /** @return array<string, mixed> */
    private function scheduleDto(?Schedule $schedule): array
    {
        return [
            'starts_at' => $schedule?->starts_at?->toIso8601String(),
            'ends_at' => $schedule?->ends_at?->toIso8601String(),
            'title' => $schedule?->title,
            'venue' => $schedule?->venue === null ? null : ['name' => $schedule->venue->name, 'location' => $schedule->venue->location],
        ];
    }

    /** @return array<string, mixed> */
    private function sourceDto(?CompetitionRuleMetadata $metadata): array
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
