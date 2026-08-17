<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\EntryStatus;
use App\Enums\TournamentState;
use App\Models\Entry;
use App\Models\Event;
use App\Models\RosterApproval;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CoachAssignmentResolver;
use App\Services\RosterReadiness;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionEntryStatus
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RosterReadiness $readiness,
        private readonly CoachAssignmentResolver $coaches,
    ) {}

    public function handle(User $actor, Event $event, Entry $entry, EntryStatus $target, ?string $reason = null, bool $rosterReviewConfirmed = false): Entry
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived() || $entry->eventId() !== (int) $event->getKey()) {
            throw new AuthorizationException('The active Global Admin is required for this Event.');
        }

        $reason = trim((string) $reason) ?: null;

        return DB::transaction(function () use ($actor, $event, $entry, $target, $reason, $rosterReviewConfirmed): Entry {
            $record = Entry::query()->with([
                'division.governingRuleVersion',
                'division.tournaments',
                'rosterMembers.participant',
                'eligibilityRecords',
            ])->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $from = $record->entryStatus();
            $published = $record->division->tournaments->contains(
                fn ($tournament): bool => $tournament->tournamentState() === TournamentState::Published,
            );

            if ($from === $target) {
                return $record;
            }

            if ($published && ! in_array($target, [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)) {
                throw ValidationException::withMessages(['status' => 'Published Entries can only be withdrawn or disqualified; tournament history is preserved.']);
            }

            if (in_array($target, [EntryStatus::Withdrawn, EntryStatus::Disqualified], true) && $reason === null) {
                throw ValidationException::withMessages(['reason' => 'A reason is required to withdraw or disqualify an Entry.']);
            }

            if ($from === EntryStatus::Locked && $target === EntryStatus::Active && $reason === null) {
                throw ValidationException::withMessages(['reason' => 'A reason is required before changing an approved team sheet. Any preview draw must then be made again.']);
            }

            if (in_array($from, [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)
                && $target === EntryStatus::Active
                && $reason === null) {
                throw ValidationException::withMessages(['reason' => 'A correction reason is required to restore a withdrawn or disqualified Entry.']);
            }

            if ($target === EntryStatus::Locked) {
                if (! $rosterReviewConfirmed) throw ValidationException::withMessages(['roster_review_confirmed' => 'Confirm that the roster and required documents were reviewed before approval.']);
                $readiness = $this->readiness->forEntry($record);
                if (! $readiness['ready']) {
                    throw ValidationException::withMessages(['entry' => implode(' ', $readiness['blockers'])]);
                }
            } elseif (! $this->isAllowedTransition($from, $target)) {
                throw ValidationException::withMessages(['status' => "An Entry cannot move from {$from->value} to {$target->value}."]);
            }

            $before = [
                'status' => $from->value,
                'locked_at' => $record->locked_at?->toIso8601String(),
            ];
            $record->update([
                'status' => $target,
                'locked_at' => $target === EntryStatus::Locked ? now() : null,
                'locked_by' => $target === EntryStatus::Locked ? $actor->getKey() : null,
                'status_reason' => $reason,
            ]);
            if ($target === EntryStatus::Locked) {
                $players = $record->rosterMembers->filter(fn ($member) => $member->is_active && in_array($member->roleType()->value, ['student_athlete', 'reserve'], true))->map(fn ($member) => ['participant_id' => (string) $member->participant_id, 'display_name' => $member->participant?->display_name, 'role' => $member->roleType()->value])->values()->all();
                $coaches = $this->coaches->forEntry($record)->map(fn ($assignment) => ['participant_id' => (string) $assignment->participant_id, 'display_name' => $assignment->participant?->display_name, 'coach_type' => $assignment->coach_type->value, 'title' => $assignment->title, 'scope_type' => $assignment->scope_type->value, 'scope_key' => $assignment->scope_key])->values()->all();
                $approval = RosterApproval::create([
                    'event_id' => $event->getKey(), 'entry_id' => $record->getKey(), 'revision' => ((int) $record->rosterApprovals()->max('revision')) + 1,
                    'players_snapshot' => $players, 'coaches_snapshot' => $coaches,
                    'limits_snapshot' => ['minimum' => $record->division->governingRuleVersion?->min_roster_size, 'maximum' => $record->division->governingRuleVersion?->max_roster_size, 'roles' => $record->division->governingRuleVersion?->roster_role_limits ?? []],
                    'source_context' => ['source_reference' => $record->division->governingRuleVersion?->source_reference], 'approved_by' => $actor->getKey(), 'approved_at' => now(),
                ]);
                $this->audit->record($actor, AuditAction::RosterApproved, $approval, $event, after: ['entry_id' => (string) $record->getKey(), 'revision' => $approval->revision, 'player_count' => count($players), 'coach_count' => count($coaches)]);
            }
            $this->audit->record(
                $actor,
                AuditAction::EntryStatusChanged,
                $record,
                $event,
                before: $before,
                after: [
                    'status' => $target->value,
                    'locked_at' => $record->locked_at?->toIso8601String(),
                ],
                reason: $reason,
                context: [
                    'tournament_published' => $published,
                    'preview_requires_redraw' => $record->division->tournaments->contains(
                        fn ($tournament): bool => in_array($tournament->tournamentState(), [TournamentState::Preview, TournamentState::Uncontested], true),
                    ),
                ],
            );

            return $record->fresh(['rosterMembers.participant', 'rosterApprovals']);
        });
    }

    private function isAllowedTransition(EntryStatus $from, EntryStatus $target): bool
    {
        return match ($from) {
            EntryStatus::Draft => in_array($target, [EntryStatus::Active, EntryStatus::Locked, EntryStatus::Withdrawn, EntryStatus::Disqualified], true),
            EntryStatus::Active => in_array($target, [EntryStatus::Draft, EntryStatus::Locked, EntryStatus::Withdrawn, EntryStatus::Disqualified], true),
            EntryStatus::Locked => in_array($target, [EntryStatus::Active, EntryStatus::Withdrawn, EntryStatus::Disqualified], true),
            EntryStatus::Withdrawn, EntryStatus::Disqualified => $target === EntryStatus::Active,
        };
    }
}
