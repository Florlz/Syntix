<?php

namespace App\Actions\Identity;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\GrantEventRole;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ProvisionEventScorer
{
    /**
     * @param  array{name: string, email: string}  $attributes
     * @return array<string, mixed>
     */
    public function handle(
        User $actor,
        Event $event,
        array $attributes,
        EventRole $role,
        ScoringAssignmentScope $scope,
        Model $target,
    ): array {
        $this->assertCompatible($role, $scope);

        return DB::transaction(function () use ($actor, $event, $attributes, $role, $scope, $target): array {
            $result = (new ProvisionUser)->handle($actor, $event, $attributes);
            $membership = (new GrantEventRole)->handle(
                $actor,
                $event,
                $result['user'],
                $role,
                'Provisioned for an exact Event scoring assignment.',
            );
            $assignment = (new GrantScoringAssignment)->handle(
                $actor,
                $event,
                $result['user'],
                $scope,
                $target,
                'Provisioned with the scorer account.',
            );

            return $result + compact('membership', 'assignment');
        });
    }

    private function assertCompatible(EventRole $role, ScoringAssignmentScope $scope): void
    {
        $allowed = match ($role) {
            EventRole::Judge => [
                ScoringAssignmentScope::CompetitionDivision,
                ScoringAssignmentScope::EntryScorecard,
            ],
            EventRole::Tabulator => [
                ScoringAssignmentScope::CompetitionDivision,
                ScoringAssignmentScope::Contest,
            ],
            EventRole::Admin => [],
        };

        if (! in_array($scope, $allowed, true)) {
            throw new \DomainException('The selected assignment scope is not valid for that Event Role.');
        }
    }
}
