<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\CoachAssignmentScope;
use App\Enums\CoachType;
use App\Models\CoachAssignment;
use App\Models\CoachCapacityRule;
use App\Models\Division;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveCoachAssignment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Event $event, Participant $participant, CoachType $type, CoachAssignmentScope $scope, string $scopeKey, ?string $title, ?string $notes): CoachAssignment
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived() || (int) $participant->event_id !== (int) $event->getKey()) throw new AuthorizationException('The coach must belong to this mutable Event.');
        $title = trim((string) $title) ?: 'Coach';
        $notes = trim((string) $notes) ?: null;

        if ($scope === CoachAssignmentScope::Division) {
            $division = Division::query()->with('competition')->findOrFail((int) $scopeKey);
            if ((int) $division->competition->event_id !== (int) $event->getKey()) throw new AuthorizationException('The Division must belong to this Event.');
        } elseif (! in_array($scopeKey, ['literary', 'musical', 'dance', 'visual_arts', 'special', 'e_games'], true)) {
            throw ValidationException::withMessages(['scope_key' => 'Choose a valid programme family.']);
        }

        return DB::transaction(function () use ($actor, $event, $participant, $type, $scope, $scopeKey, $title, $notes): CoachAssignment {
            $existing = CoachAssignment::query()->where('participant_id', $participant->getKey())->where('scope_type', $scope->value)->where('scope_key', $scopeKey)->lockForUpdate()->first();
            $rule = CoachCapacityRule::query()->where('event_id', $event->getKey())->where('scope_type', $scope->value)->where('scope_key', $scopeKey)->first();
            $maximum = $type === CoachType::Student ? $rule?->student_coach_max : $rule?->faculty_coach_max;
            if ($maximum === null && $scope === CoachAssignmentScope::Division) {
                $division = Division::query()->with('governingRuleVersion')->findOrFail((int) $scopeKey);
                $maximum = data_get($division->governingRuleVersion?->roster_role_limits, $type->value);
            }
            $currentCount = CoachAssignment::query()->where('event_id', $event->getKey())->where('event_delegation_id', $participant->event_delegation_id)->where('scope_type', $scope->value)->where('scope_key', $scopeKey)->where('coach_type', $type->value)->where('is_active', true)->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))->count();
            if ($maximum !== null && $currentCount >= (int) $maximum) throw ValidationException::withMessages(['coach_type' => "This scope has reached its {$maximum} ".str_replace('_', ' ', $type->value).' limit.']);

            $before = $existing?->only(['coach_type', 'title', 'scope_type', 'scope_key', 'is_active']) ?? [];
            $assignment = $existing ?? new CoachAssignment(['event_id' => $event->getKey(), 'event_delegation_id' => $participant->event_delegation_id, 'participant_id' => $participant->getKey(), 'scope_type' => $scope, 'scope_key' => $scopeKey]);
            $assignment->fill(['coach_type' => $type, 'title' => $title, 'is_active' => true, 'notes' => $notes, 'created_by' => $assignment->created_by ?: $actor->getKey(), 'deactivated_at' => null])->save();
            $this->audit->record($actor, AuditAction::CoachAssignmentSaved, $assignment, $event, before: $before, after: $assignment->only(['coach_type', 'title', 'scope_type', 'scope_key', 'is_active']));
            return $assignment->fresh('participant');
        });
    }
}
