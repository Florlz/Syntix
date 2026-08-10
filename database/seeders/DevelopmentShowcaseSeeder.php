<?php

namespace Database\Seeders;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Enums\AccountState;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\DivisionPlacement;
use App\Models\DivisionPlacementItem;
use App\Models\EligibilityRecord;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\OfficialContestOutcome;
use App\Models\Participant;
use App\Models\ResultSubmission;
use App\Models\RosterMember;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\ScoreLedgerEntry;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DevelopmentShowcaseSeeder may run only in local or testing environments.');
        }

        $admin = User::query()->where('is_global_admin', true)->firstOrFail();
        $event = Event::query()->where('slug', 'siklab-2026')->firstOrFail();
        (new ApplySiklab2025Programme)->handle($admin, $event);

        if (in_array($event->eventState()->value, ['preparation', 'configuration'], true)) {
            $event->forceFill(['state' => 'live', 'starts_at' => $event->starts_at ?? now()->startOfDay()])->save();
        }

        DB::transaction(function () use ($admin, $event): void {
            $tabulator = User::query()->firstOrCreate(
                ['email' => 'tabulator@syntix.test'],
                ['name' => 'SIKLAB Showcase Tabulator', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'account_state' => AccountState::Active],
            );
            EventUserRole::query()->firstOrCreate(
                ['event_id' => $event->getKey(), 'user_id' => $tabulator->getKey(), 'role' => 'tabulator', 'revoked_at' => null],
                ['granted_by' => $admin->getKey(), 'granted_at' => now(), 'reason' => 'Development sports showcase'],
            );

            $delegations = $event->delegations()->orderBy('id')->get();
            foreach ($delegations as $index => $delegation) {
                foreach ([1, 2] as $slot) {
                    Participant::query()->firstOrCreate(
                        ['event_id' => $event->getKey(), 'student_number' => sprintf('DEMO-%02d-%d', $index + 1, $slot)],
                        ['event_delegation_id' => $delegation->getKey(), 'student_number_normalized' => sprintf('DEMO-%02d-%d', $index + 1, $slot), 'display_name' => 'Showcase Athlete '.($index * 2 + $slot), 'is_active' => true, 'created_by' => $admin->getKey()],
                    );
                }
            }

            $venue = Venue::query()->firstOrCreate(
                ['event_id' => $event->getKey(), 'name' => 'CSPC Activity Center'],
                ['code' => 'CAC', 'location' => 'Main Campus', 'description' => 'Development showcase venue', 'is_active' => true],
            );

            $showcase = [
                ['Basketball', 'Men', 'CAS', 'CCS', 'Basketball Men — CAS vs CCS', 'live', ['home' => 68, 'away' => 64, 'period' => 'Q4', 'status' => 'Live']],
                ['Volleyball', 'Women', 'CHS', 'CEA', 'Volleyball Women — CHS vs CEA', 'live', ['home' => 2, 'away' => 1, 'set' => 4, 'status' => 'Live']],
                ['Badminton', 'Men', 'CTDE', 'CTHBM', 'Badminton Men — CTDE vs CTHBM', 'live', ['home' => 1, 'away' => 1, 'phase' => 'Deciding rubber', 'status' => 'Live']],
                ['Table Tennis', 'Women', 'Buhi', 'CAS', 'Table Tennis Women — Buhi vs CAS', 'review', ['home' => 2, 'away' => 1, 'status' => 'Complete']],
                ['Chess', 'Women', 'CAS', 'CCS', 'Chess Women — CAS vs CCS', 'official', ['home' => 1, 'away' => 0, 'status' => 'Official']],
            ];

            foreach ($showcase as $offset => [$sportName, $divisionName, $homeCode, $awayCode, $contestName, $mode, $score]) {
                $division = $event->competitions()->where('name', $sportName)->firstOrFail()->divisions()->where('name', $divisionName)->firstOrFail();
                $rule = $division->governingRuleVersion()->first() ?? $division->ruleVersions()->latest('version')->firstOrFail();
                $home = $delegations->first(fn ($d) => strcasecmp((string) $d->abbreviation, $homeCode) === 0 || strcasecmp((string) $d->name, $homeCode) === 0);
                $away = $delegations->first(fn ($d) => strcasecmp((string) $d->abbreviation, $awayCode) === 0 || strcasecmp((string) $d->name, $awayCode) === 0);
                if (! $home || ! $away) continue;
                $homeEntry = $this->entry($event, $division, $home, $admin);
                $awayEntry = $this->entry($event, $division, $away, $admin);
                $contest = Contest::query()->firstOrCreate(
                    ['competition_division_id' => $division->getKey(), 'name' => $contestName],
                    ['competition_rule_version_id' => $rule->getKey(), 'state' => $mode === 'live' ? 'live' : 'completed', 'revision' => 1, 'live_payload' => $score, 'result_payload' => $mode === 'live' ? null : $score, 'started_at' => now()->subMinutes(30), 'completed_at' => $mode === 'live' ? null : now()->subMinutes(5), 'completed_by' => $mode === 'live' ? null : $tabulator->getKey()],
                );
                ContestEntry::query()->firstOrCreate(['contest_id' => $contest->getKey(), 'entry_id' => $homeEntry->getKey()], ['slot' => 1]);
                ContestEntry::query()->firstOrCreate(['contest_id' => $contest->getKey(), 'entry_id' => $awayEntry->getKey()], ['slot' => 2]);
                ScoringAssignment::query()->firstOrCreate(
                    ['event_id' => $event->getKey(), 'user_id' => $tabulator->getKey(), 'scope_type' => 'contest', 'contest_id' => $contest->getKey(), 'revoked_at' => null],
                    ['granted_by' => $admin->getKey(), 'granted_at' => now(), 'reason' => 'Development sports showcase'],
                );
                $schedule = Schedule::query()->firstOrCreate(
                    ['event_id' => $event->getKey(), 'contest_id' => $contest->getKey()],
                    ['competition_division_id' => $division->getKey(), 'venue_id' => $venue->getKey(), 'title' => $contestName, 'starts_at' => now()->startOfDay()->addHours(8 + $offset), 'ends_at' => now()->startOfDay()->addHours(9 + $offset), 'status' => $mode === 'live' ? 'scheduled' : 'completed'],
                );
                SchedulePublication::query()->firstOrCreate(
                    ['schedule_id' => $schedule->getKey(), 'revision' => 1],
                    ['competition_name' => $sportName, 'division_name' => $divisionName, 'title' => $contestName, 'starts_at' => $schedule->starts_at, 'ends_at' => $schedule->ends_at, 'status' => $schedule->status, 'venue_name' => $venue->name, 'venue_location' => $venue->location, 'state' => 'published', 'published_by' => $admin->getKey(), 'published_at' => now()],
                );
                if ($mode !== 'live') $this->seedOutcome($contest, $tabulator, $admin, $homeEntry, $score, $mode === 'official');
            }

            $this->seedChessLedger($event, $admin, $delegations);
        });
    }

    private function entry(Event $event, $division, $delegation, User $admin): Entry
    {
        $entry = Entry::query()->firstOrCreate(
            ['competition_division_id' => $division->getKey(), 'event_delegation_id' => $delegation->getKey()],
            ['code' => 'DEMO-'.$delegation->abbreviation, 'name' => $delegation->abbreviation.' '.$division->competition->name.' '.$division->name, 'entry_mode' => 'team', 'status' => 'locked', 'locked_at' => now(), 'locked_by' => $admin->getKey()],
        );
        $athletes = Participant::query()->where('event_id', $event->getKey())->where('event_delegation_id', $delegation->getKey())->get();
        foreach ($athletes as $index => $athlete) {
            RosterMember::query()->firstOrCreate(['entry_id' => $entry->getKey(), 'participant_id' => $athlete->getKey()], ['role' => 'student_athlete', 'display_order' => $index, 'is_active' => true]);
            EligibilityRecord::query()->firstOrCreate(['event_id' => $event->getKey(), 'entry_id' => $entry->getKey(), 'participant_id' => $athlete->getKey()], ['status' => 'eligible', 'reason' => 'Development showcase', 'checked_by' => $admin->getKey(), 'checked_at' => now()]);
        }
        return $entry;
    }

    private function seedOutcome(Contest $contest, User $tabulator, User $admin, Entry $winner, array $score, bool $approved): void
    {
        $submission = ResultSubmission::query()->firstOrCreate(
            ['contest_id' => $contest->getKey(), 'contest_revision' => $contest->revision],
            ['submitted_by' => $tabulator->getKey(), 'state' => $approved ? 'approved' : 'submitted', 'payload' => $score + ['winner_entry_id' => $winner->getKey(), 'outcome_type' => 'played'], 'submitted_at' => now(), 'approved_at' => $approved ? now() : null],
        );
        if ($approved) OfficialContestOutcome::query()->firstOrCreate(
            ['contest_id' => $contest->getKey(), 'result_submission_id' => $submission->getKey()],
            ['revision' => $contest->revision, 'state' => 'approved', 'outcome_type' => 'played', 'winner_entry_id' => $winner->getKey(), 'payload' => $score, 'approved_by' => $admin->getKey(), 'approved_at' => now(), 'reason' => 'Development showcase'],
        );
    }

    private function seedChessLedger(Event $event, User $admin, $delegations): void
    {
        $division = $event->competitions()->where('name', 'Chess')->firstOrFail()->divisions()->where('name', 'Women')->firstOrFail();
        if ($division->placements()->where('state', 'approved')->exists()) return;
        $rule = $division->governingRuleVersion()->first() ?? $division->ruleVersions()->latest('version')->firstOrFail();
        $placement = DivisionPlacement::create(['competition_division_id' => $division->getKey(), 'competition_rule_version_id' => $rule->getKey(), 'revision' => 1, 'state' => 'approved', 'evidence' => ['source' => 'development_showcase'], 'submitted_by' => $admin->getKey(), 'approved_by' => $admin->getKey(), 'submitted_at' => now(), 'approved_at' => now(), 'reason' => 'Development showcase']);
        $points = ['CAS' => 8, 'CCS' => 6, 'CHS' => 4];
        foreach ($delegations as $rank => $delegation) {
            $entry = $this->entry($event, $division, $delegation, $admin); $amount = $points[$delegation->abbreviation] ?? 2;
            $item = DivisionPlacementItem::create(['division_placement_id' => $placement->getKey(), 'entry_id' => $entry->getKey(), 'event_delegation_id' => $delegation->getKey(), 'rank' => $rank + 1, 'placement_key' => 'showcase_'.($rank + 1), 'championship_points' => $amount, 'participation_eligible' => true]);
            ScoreLedgerEntry::query()->firstOrCreate(['source_key' => 'showcase:chess-women:'.$delegation->getKey()], ['event_id' => $event->getKey(), 'event_delegation_id' => $delegation->getKey(), 'division_placement_id' => $placement->getKey(), 'division_placement_item_id' => $item->getKey(), 'entry_type' => 'award', 'amount' => $amount, 'source_revision' => 1, 'created_by' => $admin->getKey(), 'committed_at' => now(), 'reason' => 'Development showcase']);
        }
    }
}
