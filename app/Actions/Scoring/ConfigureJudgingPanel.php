<?php

namespace App\Actions\Scoring;

use App\Actions\Assignments\RevokeScoringAssignment;
use App\Actions\Assignments\GrantScoringAssignment;
use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Contest;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ConfigureJudgingPanel
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /** @param iterable<User> $judges */
    public function handle(User $actor, Contest $contest, iterable $judges): Contest
    {
        return DB::transaction(function () use ($actor, $contest, $judges): Contest {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();
            $contest->load('division.competition.event', 'entries', 'ruleVersion');
            $event = Event::query()->whereKey($contest->eventId())->lockForUpdate()->firstOrFail();
            EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can configure a judging panel.');

            if ($contest->isJudgingPanelLocked()) {
                throw new \DomainException('The judging panel is locked.');
            }

            /** @var Collection<int, User> $selected */
            $selected = collect($judges)
                ->map(fn (User $judge): User => User::query()->whereKey($judge->getKey())->firstOrFail())
                ->unique(fn (User $judge): int => (int) $judge->getKey())
                ->values();

            foreach ($selected as $judge) {
                if (! $judge->isActive() || ! $judge->hasActiveEventRole($event, EventRole::Judge)) {
                    throw new \DomainException('Every panel member requires an active account and Judge role.');
                }
            }

            $selectedIds = $selected->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $currentIds = $contest->scorecards()->whereNotNull('judge_id')->pluck('judge_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
            $normalizedSelectedIds = collect($selectedIds)->sort()->values()->all();
            $scoringExists = $contest->scorecards()->where(function ($query): void {
                $query->where('revision', '>', 0)->orWhereNotNull('calculated_total')->orWhereHas('values');
            })->exists();
            if ($scoringExists && $currentIds !== $normalizedSelectedIds) {
                throw new \DomainException('Every panel change after scoring begins requires an audited correction.');
            }
            $removed = $contest->scorecards()
                ->whereNotNull('judge_id')
                ->when($selectedIds !== [], fn ($query) => $query->whereNotIn('judge_id', $selectedIds))
                ->when($selectedIds === [], fn ($query) => $query)
                ->with('values')
                ->get();

            foreach ($removed as $scorecard) {
                if ((int) $scorecard->revision !== 0 || $scorecard->calculated_total !== null || $scorecard->values->isNotEmpty()) {
                    throw new \DomainException('Panel changes after scoring begins require an audited correction.');
                }
            }

            if ($removed->isNotEmpty()) {
                $revoke = new RevokeScoringAssignment;

                foreach ($removed as $scorecard) {
                    ScoringAssignment::query()
                        ->active()
                        ->where('entry_scorecard_id', $scorecard->getKey())
                        ->get()
                        ->each(fn (ScoringAssignment $assignment) => $revoke->handle(
                            $assignment,
                            $actor,
                            'Judge removed from the judging panel.',
                        ));

                    // Keep the scorecard row as a detached operational record.
                    // The assignment history retains its exact target through
                    // the foreign key, while a future panel can create a new
                    // scorecard for the same Judge/entry pair.
                    if ((int) $scorecard->revision === 0
                        && $scorecard->calculated_total === null
                        && $scorecard->values->isEmpty()) {
                        $scorecard->update(['judge_id' => null]);
                    }
                }
            }

            foreach ($selected as $judge) {
                foreach ($contest->entries as $contestEntry) {
                    $scorecard = EntryScorecard::query()->firstOrCreate(
                        [
                            'contest_id' => $contest->getKey(),
                            'entry_id' => $contestEntry->entry_id,
                            'judge_id' => $judge->getKey(),
                        ],
                        [
                            'competition_rule_version_id' => $contest->competition_rule_version_id,
                            'entry_reference' => (string) $contestEntry->entry_id,
                            'state' => 'draft',
                            'revision' => 0,
                        ],
                    );

                    $assigned = ScoringAssignment::query()
                        ->where('event_id', $event->getKey())
                        ->where('user_id', $judge->getKey())
                        ->where('scope_type', ScoringAssignmentScope::EntryScorecard->value)
                        ->where('entry_scorecard_id', $scorecard->getKey())
                        ->whereNull('revoked_at')
                        ->exists();

                    if (! $assigned) {
                        (new GrantScoringAssignment)->handle(
                            $actor,
                            $event,
                            $judge,
                            ScoringAssignmentScope::EntryScorecard,
                            $scorecard,
                            'Judging panel configuration',
                        );
                    }
                }
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::JudgingPanelConfigured,
                $contest,
                $event,
                after: ['judge_ids' => $selectedIds, 'entry_count' => $contest->entries->count()],
            );

            return $contest->fresh(['scorecards', 'entries']);
        });
    }
}
