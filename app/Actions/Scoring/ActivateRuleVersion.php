<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Enums\RuleVersionState;
use App\Models\CompetitionRuleVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ActivateRuleVersion
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, CompetitionRuleVersion $version): CompetitionRuleVersion
    {
        return DB::transaction(function () use ($actor, $version): CompetitionRuleVersion {
            $version = CompetitionRuleVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $version->load('division.competition.event');
            $event = $version->division?->competition?->event;

            if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
                throw new AuthorizationException('Only an event Admin can activate a rule version.');
            }

            if ($version->lifecycleState() !== RuleVersionState::Draft) {
                throw new \DomainException('Only draft rule versions can be activated.');
            }

            if ($version->division?->hasScoringStarted()) {
                throw new \DomainException('A live Division cannot activate a replacement rule without an approved migration.');
            }

            $version->assertReadyForActivation();

            $previous = $version->division->ruleVersions()
                ->where('is_governing', true)
                ->where($version->getKeyName(), '!=', $version->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($previous as $oldVersion) {
                $oldVersion->update([
                    'is_governing' => false,
                    'lifecycle_state' => RuleVersionState::Superseded,
                ]);
            }

            $version->update([
                'lifecycle_state' => RuleVersionState::ActivatedEditable,
                'is_governing' => true,
                'activated_by' => $actor->getKey(),
                'activated_at' => now(),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::RuleVersionActivated,
                $version,
                before: ['lifecycle_state' => RuleVersionState::Draft->value],
                after: [
                    'lifecycle_state' => RuleVersionState::ActivatedEditable->value,
                    'is_governing' => true,
                ],
            );

            return $version->fresh();
        });
    }
}
