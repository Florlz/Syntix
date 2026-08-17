<?php

namespace Tests\Unit;

use App\Enums\DivisionPlacementState;
use App\Enums\EntryStatus;
use App\Enums\ParticipantMode;
use App\Enums\PublicationState;
use App\Enums\ResultSubmissionState;
use App\Enums\ScheduleStatus;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\ResultSubmission;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\User;
use App\Services\SportWorkspaceReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportWorkspaceReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_are_not_started_without_contests_submissions_or_placements(): void
    {
        [$sport, $division] = $this->workspace();

        $payload = app(SportWorkspaceReadModel::class)->forSport($sport);

        $this->assertSame('not_started', $payload['divisions'][0]['results_state']);
        $this->assertSame(0, $payload['sport']['entry_count']);
        $this->assertSame(0, $payload['divisions'][0]['player_count']);
    }

    public function test_approved_contest_result_without_approved_placement_is_in_progress(): void
    {
        [$sport, $division, $user] = $this->workspace();
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $user->getKey(),
            'state' => ResultSubmissionState::Approved,
            'contest_revision' => 1,
            'payload' => ['outcome_type' => 'played'],
            'approved_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('in_progress', $payload['results_state']);
    }

    public function test_submitted_result_or_placement_needs_review(): void
    {
        [$sport, $division, $user, $rule] = $this->workspace();
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $user->getKey(),
            'state' => ResultSubmissionState::Submitted,
            'contest_revision' => 1,
            'payload' => ['outcome_type' => 'played'],
            'submitted_at' => now(),
        ]);
        DivisionPlacement::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $rule->getKey(),
            'state' => DivisionPlacementState::Submitted,
            'submitted_by' => $user->getKey(),
            'submitted_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('pending_review', $payload['results_state']);
    }

    public function test_only_an_approved_final_placement_completes_results(): void
    {
        [$sport, $division, $user, $rule] = $this->workspace();
        DivisionPlacement::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $rule->getKey(),
            'state' => DivisionPlacementState::Approved,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('complete', $payload['results_state']);
    }

    public function test_readiness_counts_only_participating_entries(): void
    {
        [$sport, $division] = $this->workspace();

        foreach ([EntryStatus::Locked, EntryStatus::Active, EntryStatus::Withdrawn, EntryStatus::Disqualified] as $index => $status) {
            $delegation = EventDelegation::factory()->create(['event_id' => $sport->event_id]);
            Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => 'Entry '.($index + 1),
                'entry_mode' => ParticipantMode::Team,
                'status' => $status,
            ]);
        }

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame(4, $payload['entry_count']);
        $this->assertSame(2, $payload['participating_entry_count']);
        $this->assertSame(1, $payload['locked_entry_count']);
        $this->assertSame(1, $payload['unlocked_entry_count']);
    }

    public function test_past_published_schedules_remain_published_without_a_next_activity(): void
    {
        [$sport, $division, $user] = $this->workspace();
        $this->publishedSchedule($division, $user, now()->subDay());

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('published', $payload['schedule_state']);
        $this->assertNull($payload['next_schedule']);
    }

    public function test_past_schedules_with_unpublished_changes_are_drafts(): void
    {
        [$sport, $division, $user] = $this->workspace();
        $schedule = $this->publishedSchedule($division, $user, now()->subDay());
        $schedule->update(['title' => 'Changed after publication']);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('draft', $payload['schedule_state']);
    }

    public function test_next_schedule_skips_cancelled_future_activities(): void
    {
        [$sport, $division, $user] = $this->workspace();
        $this->publishedSchedule($division, $user, now()->addDay(), ScheduleStatus::Cancelled, 'Cancelled game');
        $this->publishedSchedule($division, $user, now()->addDays(2), ScheduleStatus::Scheduled, 'Next playable game');

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('published', $payload['schedule_state']);
        $this->assertSame('Next playable game', $payload['next_schedule']['title']);
    }

    /** @return array{0: Competition, 1: Division, 2: User, 3: CompetitionRuleVersion} */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $user->getKey()]);
        $sport = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $sport->getKey()]);
        $rule = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => 'frozen',
            'is_governing' => true,
            'created_by' => $user->getKey(),
        ]);

        return [$sport, $division, $user, $rule];
    }

    private function publishedSchedule(Division $division, User $user, \DateTimeInterface $startsAt, ScheduleStatus $status = ScheduleStatus::Scheduled, string $title = 'Scheduled activity'): Schedule
    {
        $division->loadMissing('competition');
        $schedule = Schedule::create([
            'event_id' => $division->competition->event_id,
            'competition_division_id' => $division->getKey(),
            'title' => $title,
            'starts_at' => $startsAt,
            'status' => $status,
        ]);
        SchedulePublication::create([
            'schedule_id' => $schedule->getKey(),
            'revision' => 1,
            'competition_name' => $division->competition->name,
            'division_name' => $division->name,
            'title' => $title,
            'starts_at' => $startsAt,
            'status' => $status,
            'state' => PublicationState::Published,
            'published_by' => $user->getKey(),
            'published_at' => now(),
        ]);

        return $schedule;
    }
}
