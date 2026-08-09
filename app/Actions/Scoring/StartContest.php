<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Enums\RuleVersionState;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class StartContest
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, Contest $contest): Contest
    {
        if (! $actor->canScoreContest($contest)) {
            throw new AuthorizationException('The Tabulator is not assigned to this contest.');
        }

        return DB::transaction(function () use ($actor, $contest): Contest {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();

            if ($contest->state !== ContestState::Scheduled) {
                throw new \DomainException('Only scheduled contests can start.');
            }

            $division = Division::query()->whereKey($contest->competition_division_id)->lockForUpdate()->firstOrFail();
            $version = CompetitionRuleVersion::query()
                ->where('competition_division_id', $division->getKey())
                ->where('is_governing', true)
                ->lockForUpdate()
                ->first();

            if ($version === null || $version->lifecycleState() !== RuleVersionState::ActivatedEditable) {
                throw new \DomainException('The Division has no activated governing rule version.');
            }

            $version->assertReadyForActivation();
            $version->update([
                'lifecycle_state' => RuleVersionState::Frozen,
                'frozen_at' => now(),
            ]);
            $division->update(['scoring_started_at' => now()]);

            $contest->update([
                'competition_rule_version_id' => $version->getKey(),
                'state' => ContestState::Live,
                'started_at' => now(),
                'revision' => ((int) $contest->revision) + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestStarted,
                $contest,
                event: $contest->division?->competition?->event,
                before: ['state' => ContestState::Scheduled->value],
                after: [
                    'state' => ContestState::Live->value,
                    'rule_version_id' => $version->getKey(),
                    'revision' => $contest->revision,
                ],
            );

            return $contest->fresh();
        });
    }
}
