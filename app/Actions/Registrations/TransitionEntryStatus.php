<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentState;
use App\Models\Entry;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionEntryStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Event $event, Entry $entry, EntryStatus $target, ?string $reason = null): Entry
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived() || $entry->eventId() !== (int) $event->getKey()) {
            throw new AuthorizationException('The active Global Admin is required for this Event.');
        }

        $reason = trim((string) $reason) ?: null;

        return DB::transaction(function () use ($actor, $event, $entry, $target, $reason): Entry {
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
                throw ValidationException::withMessages(['reason' => 'A reason is required to unlock an Entry. Any preview draw must then be redrawn.']);
            }

            if (in_array($from, [EntryStatus::Withdrawn, EntryStatus::Disqualified], true)
                && $target === EntryStatus::Active
                && $reason === null) {
                throw ValidationException::withMessages(['reason' => 'A correction reason is required to restore a withdrawn or disqualified Entry.']);
            }

            if ($target === EntryStatus::Locked) {
                $this->assertReadyToLock($record);
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

            return $record->fresh(['rosterMembers.participant', 'eligibilityRecords']);
        });
    }

    private function assertReadyToLock(Entry $entry): void
    {
        $rule = $entry->division->governingRuleVersion
            ?? $entry->division->ruleVersions()->latest('version')->first();

        if ($rule === null) {
            throw ValidationException::withMessages(['entry' => 'A governing roster rule is required before lock.']);
        }

        $athleteRoles = [RosterMemberRole::StudentAthlete, RosterMemberRole::Reserve];
        $athletes = $entry->rosterMembers
            ->filter(fn ($member): bool => $member->is_active && in_array($member->roleType(), $athleteRoles, true));

        if ($athletes->count() < (int) ($rule->min_roster_size ?? 1)) {
            throw ValidationException::withMessages(['entry' => "At least {$rule->min_roster_size} active athlete is required before lock."]);
        }

        if ($rule->max_roster_size !== null && $athletes->count() > (int) $rule->max_roster_size) {
            throw ValidationException::withMessages(['entry' => "The roster exceeds its {$rule->max_roster_size}-athlete limit."]);
        }

        $notEligible = $athletes->filter(function ($member) use ($entry): bool {
            $eligibility = $entry->eligibilityRecords->firstWhere('participant_id', $member->participant_id);

            return $eligibility === null || $eligibility->eligibilityStatus() !== EligibilityStatus::Eligible;
        });

        if ($notEligible->isNotEmpty()) {
            throw ValidationException::withMessages(['entry' => 'Every active athlete must be marked eligible before lock.']);
        }

        if ($athletes->contains(fn ($member): bool => ! (bool) $member->participant?->is_active)) {
            throw ValidationException::withMessages(['entry' => 'Inactive Participants cannot be included when an Entry is locked.']);
        }
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
