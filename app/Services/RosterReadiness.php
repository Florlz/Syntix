<?php

namespace App\Services;

use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentState;
use App\Models\Entry;

/**
 * The single source of truth for the pre-lock copy shown to an administrator.
 * This deliberately reports operational roster blockers, rather than exposing
 * the implementation details of the competition rule configuration.
 */
final class RosterReadiness
{
    public function __construct(private readonly CoachAssignmentResolver $coaches) {}

    /** @return array{ready: bool, blockers: list<string>, notices: list<string>} */
    public function forEntry(Entry $entry): array
    {
        $entry->loadMissing([
            'division.governingRuleVersion',
            'division.tournaments',
            'rosterMembers.participant',
        ]);

        $blockers = [];
        $notices = [];
        $rule = $entry->division->governingRuleVersion
            ?? $entry->division->ruleVersions()->latest('version')->first();

        if ($rule === null) {
            $blockers[] = 'A roster rule is required before this team sheet can be locked.';

            return ['ready' => false, 'blockers' => $blockers, 'notices' => $notices];
        }

        $athleteRoles = [RosterMemberRole::StudentAthlete, RosterMemberRole::Reserve];
        $activeMembers = $entry->rosterMembers->filter(fn ($member): bool => (bool) $member->is_active);
        $athletes = $activeMembers->filter(fn ($member): bool => in_array($member->roleType(), $athleteRoles, true));
        $athleteCount = $athletes->count();
        $minimum = (int) ($rule->min_roster_size ?? 1);

        if ($athleteCount < $minimum) {
            $blockers[] = "Add at least {$minimum} active athlete".($minimum === 1 ? '' : 's')." before locking.";
        } elseif ($rule->max_roster_size !== null && $athleteCount > (int) $rule->max_roster_size) {
            $maximum = (int) $rule->max_roster_size;
            $over = $athleteCount - $maximum;
            $blockers[] = "Remove {$over} athlete".($over === 1 ? '' : 's')." to meet the {$maximum}-athlete limit.";
        } elseif ($rule->max_roster_size !== null && $athleteCount < (int) $rule->max_roster_size) {
            $remaining = (int) $rule->max_roster_size - $athleteCount;
            $notices[] = "{$remaining} optional athlete place".($remaining === 1 ? '' : 's').' remain'.($remaining === 1 ? 's' : '').'.';
        }

        foreach ($athletes as $member) {
            if (! $member->participant?->is_active) {
                $name = $member->participant?->display_name ?? 'A roster participant';
                $blockers[] = "{$name} is inactive and cannot be locked on this roster.";
            }
        }

        $coachAssignments = $this->coaches->forEntry($entry);
        foreach (['student_coach', 'faculty_coach'] as $coachType) {
            $maximum = data_get($rule->roster_role_limits, $coachType);
            $count = $coachAssignments->filter(
                fn ($assignment): bool => $assignment->coach_type->value === $coachType
            )->count();
            if ($maximum !== null && $count > (int) $maximum) $blockers[] = 'Reduce '.str_replace('_', ' ', $coachType)." assignments to the configured limit of {$maximum}.";
            if ($count === 0) $notices[] = ucfirst(str_replace('_', ' ', $coachType)).' is not assigned; this does not prevent roster approval.';
        }

        if ($entry->division->tournaments->contains(fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published)) {
            $blockers[] = 'The published draw is read-only; record a withdrawal or disqualification instead of editing this team sheet.';
        } elseif ($entry->division->tournaments->contains(fn ($tournament): bool => in_array($tournament->tournamentState(), [TournamentState::Preview, TournamentState::Uncontested], true))) {
            $notices[] = 'A preview draw exists and should be regenerated after roster changes.';
        }

        if (in_array($entry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)) {
            $blockers[] = 'Restore this department roster before editing its team sheet.';
        }

        $blockers = array_values(array_unique(array_filter($blockers)));
        $notices = array_values(array_unique(array_filter($notices)));

        return ['ready' => $blockers === [], 'blockers' => $blockers, 'notices' => $notices];
    }
}
