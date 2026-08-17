<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\EntryStatus;
use App\Enums\ScoringFamily;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Event;
use App\Models\EntryScorecard;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventOperationGuard;
use Illuminate\Support\Facades\DB;

final class PrepareJudgedContest
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, Division $division): Contest
    {
        return DB::transaction(function () use ($actor, $division): Contest {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            $division->load('competition.event', 'governingRuleVersion');
            $event = Event::query()->whereKey($division->competition?->event_id)->lockForUpdate()->firstOrFail();

            EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can prepare a judged Contest.');

            if (! $division->is_active || ! $division->competition?->is_active) {
                throw new \DomainException('Only an active Competition and Division can be prepared.');
            }

            $rule = $division->governingRuleVersion
                ?? $division->ruleVersions()->latest('version')->first();
            if ($rule?->source_status === 'blocked') {
                throw new \DomainException('The source rule is blocked and cannot become scoreable.');
            }

            if ($rule === null || ! $rule->is_governing || $rule->scoringFamily() !== ScoringFamily::CriteriaBased) {
                throw new \DomainException('A governing criteria-based rule is required.');
            }

            $contest = Contest::query()->firstOrCreate(
                [
                    'competition_division_id' => $division->getKey(),
                    'competition_rule_version_id' => $rule->getKey(),
                    'discipline_id' => null,
                ],
                [
                    'name' => $division->competition->name,
                    'live_payload' => ['scoring_mode' => 'judged', 'official' => true],
                ],
            );

            $entries = $division->entries()
                ->whereIn('status', [EntryStatus::Active->value, EntryStatus::Locked->value])
                ->orderBy('id')
                ->get();

            if ($entries->isEmpty()) {
                throw new \DomainException('At least one eligible competing entry is required.');
            }

            $eligibleIds = $entries->modelKeys();
            $stale = $contest->entries()->whereNotIn('entry_id', $eligibleIds)->get();
            if ($stale->isNotEmpty()) {
                $staleCards = $contest->scorecards()->whereIn('entry_id', $stale->pluck('entry_id'))->with('values')->get();
                if ($staleCards->contains(fn ($card): bool => (int) $card->revision > 0 || $card->calculated_total !== null || $card->values->isNotEmpty())) {
                    throw new \DomainException('An ineligible prepared entry already has scoring evidence and requires an audited correction.');
                }
                ScoringAssignment::query()->whereIn('entry_scorecard_id', $staleCards->modelKeys())->delete();
                EntryScorecard::query()->whereKey($staleCards->modelKeys())->delete();
                $contest->entries()->whereKey($stale->modelKeys())->delete();
            }

            foreach ($entries as $slot => $entry) {
                $contest->entries()->firstOrCreate(
                    ['entry_id' => $entry->getKey()],
                    ['slot' => $slot + 1, 'state' => 'active'],
                );
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::JudgedContestPrepared,
                $contest,
                $event,
                after: [
                    'rule_version_id' => $rule->getKey(),
                    'entry_count' => $entries->count(),
                ],
            );

            return $contest->fresh(['entries.entry', 'ruleVersion']);
        });
    }
}
