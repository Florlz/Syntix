<?php

namespace App\Actions\Assignments;

use App\Enums\AuditAction;
use App\Models\Event;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RevokeScoringAssignment
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(ScoringAssignment $assignment, User $actor, string $reason): ScoringAssignment
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required when revoking a scoring assignment.');
        }

        return DB::transaction(function () use ($assignment, $actor, $reason): ScoringAssignment {
            $assignment = ScoringAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();
            $event = Event::query()->whereKey($assignment->event_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->hasAdminAccess($event)) {
                throw new AuthorizationException('The active Global Admin is required to revoke scoring assignments.');
            }

            if (! $assignment->isActive()) {
                throw new \DomainException('The scoring assignment is already revoked.');
            }

            $scope = $assignment->scopeType();
            $before = [
                'event_id' => $event->getKey(),
                'user_id' => $assignment->user_id,
                'scope_type' => $scope->value,
                'competition_division_id' => $assignment->competition_division_id,
                'contest_id' => $assignment->contest_id,
                'entry_scorecard_id' => $assignment->entry_scorecard_id,
                'active' => true,
            ];

            $assignment->forceFill([
                'revoked_by' => $actor->getKey(),
                'revoked_at' => now(),
                'reason' => $reason,
            ])->save();

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ScoringAssignmentRevoked,
                $assignment,
                $event,
                before: $before,
                after: [
                    'event_id' => $event->getKey(),
                    'user_id' => $assignment->user_id,
                    'scope_type' => $scope->value,
                    'competition_division_id' => $assignment->competition_division_id,
                    'contest_id' => $assignment->contest_id,
                    'entry_scorecard_id' => $assignment->entry_scorecard_id,
                    'active' => false,
                ],
                reason: $reason,
            );

            return $assignment;
        });
    }
}
