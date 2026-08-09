<?php

namespace App\Actions\Assignments;

use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Enums\EventState;
use App\Enums\ScoringAssignmentScope;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class GrantScoringAssignment
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(
        User $actor,
        Event $event,
        User $assignee,
        ScoringAssignmentScope|string $scope,
        Model $target,
        ?string $reason = null,
    ): ScoringAssignment {
        $scope = $scope instanceof ScoringAssignmentScope
            ? $scope
            : ScoringAssignmentScope::from($scope);

        return DB::transaction(function () use ($actor, $event, $assignee, $scope, $target, $reason): ScoringAssignment {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $event = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $assignee = User::query()->whereKey($assignee->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->hasActiveEventRole($event, EventRole::Admin)) {
                throw new AuthorizationException('An active event Admin is required to create scoring assignments.');
            }

            if (! $assignee->isActive() || $event->eventState() === EventState::Archived) {
                throw new AuthorizationException('Assignments require an active account and non-archived event.');
            }

            $targetEventId = ScoringAssignment::eventIdForTarget($target);

            if (! $target->exists || $targetEventId === null || $targetEventId !== (int) $event->getKey()) {
                throw new \DomainException('The assignment target must belong to the selected event.');
            }

            $requiredRole = match ($scope) {
                ScoringAssignmentScope::EntryScorecard => EventRole::Judge,
                ScoringAssignmentScope::CompetitionDivision,
                ScoringAssignmentScope::Contest => EventRole::Tabulator,
            };

            if (! $assignee->hasActiveEventRole($event, $requiredRole)) {
                throw new \DomainException('The assignee does not have the event role required by this scope.');
            }

            $targetIds = [
                'competition_division_id' => null,
                'contest_id' => null,
                'entry_scorecard_id' => null,
            ];

            match ($scope) {
                ScoringAssignmentScope::CompetitionDivision => $target instanceof Division
                    ? $targetIds['competition_division_id'] = $target->getKey()
                    : throw new \InvalidArgumentException('A competition_division scope requires a Division target.'),
                ScoringAssignmentScope::Contest => $target instanceof Contest
                    ? $targetIds['contest_id'] = $target->getKey()
                    : throw new \InvalidArgumentException('A contest scope requires a Contest target.'),
                ScoringAssignmentScope::EntryScorecard => $target instanceof EntryScorecard
                    ? $targetIds['entry_scorecard_id'] = $target->getKey()
                    : throw new \InvalidArgumentException('An entry_scorecard scope requires a scorecard target.'),
            };

            $duplicate = ScoringAssignment::query()
                ->where('event_id', $event->getKey())
                ->where('user_id', $assignee->getKey())
                ->where('scope_type', $scope->value)
                ->where(array_filter($targetIds, static fn (mixed $value): bool => $value !== null))
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new \DomainException('The assignee already has this active scoring assignment.');
            }

            $assignment = ScoringAssignment::create([
                'event_id' => $event->getKey(),
                'user_id' => $assignee->getKey(),
                'scope_type' => $scope,
                ...$targetIds,
                'granted_by' => $actor->getKey(),
                'granted_at' => now(),
                'reason' => $reason,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ScoringAssignmentGranted,
                $assignment,
                $event,
                after: [
                    'event_id' => $event->getKey(),
                    'user_id' => $assignee->getKey(),
                    'scope_type' => $scope->value,
                    ...$targetIds,
                    'active' => true,
                ],
                reason: $reason,
            );

            return $assignment;
        });
    }
}
