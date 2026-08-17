<?php

namespace Tests\Feature\Admin;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Enums\PublicationState;
use App\Enums\ScoringAssignmentScope;
use App\Enums\ScheduleStatus;
use App\Models\Competition;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_dashboard_contains_proposal_teams_rules_and_tournament_controls(): void
    {
        [$admin, $event] = $this->programme();

        $this->actingAs($admin)->get('/dashboard?event='.$event->getKey())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('event.id', (string) $event->getKey())
                ->where('capabilities.global_admin', true)
                ->has('teams', 7)
                ->has('programme', 42)
                ->where('programme.0.source_reference', fn ($value): bool => is_string($value) && $value !== '')
                ->where('summary.competitions', fn ($value): bool => is_int($value) && $value > 0)
                ->where('summary.divisions', 42)
                ->where('summary.blocked_divisions', fn ($value): bool => is_int($value) && $value >= 0)
                ->where('summary.pending_eligibility_records', 0)
                ->where('summary.schedules', 0)
                ->where('summary.published_schedules', 0)
                ->where('summary.schedule_draft_changes', 0)
                ->missing('active_tab')
                ->has('readiness', 7)
                ->where('readiness.2.key', 'registrations'));
    }

    public function test_global_dashboard_summarizes_schedule_publication_state(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->firstOrFail()->divisions()->firstOrFail();
        $draft = Schedule::query()->create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $division->getKey(),
            'title' => 'Draft activity',
            'starts_at' => now()->addDay(),
            'status' => ScheduleStatus::Scheduled,
        ]);
        $published = Schedule::query()->create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $division->getKey(),
            'title' => 'Published activity',
            'starts_at' => now()->addDays(2),
            'status' => ScheduleStatus::Scheduled,
        ]);
        SchedulePublication::query()->create([
            'schedule_id' => $published->getKey(),
            'revision' => 1,
            'competition_name' => $division->competition->name,
            'division_name' => $division->name,
            'title' => $published->title,
            'starts_at' => $published->starts_at,
            'status' => ScheduleStatus::Scheduled,
            'state' => PublicationState::Published,
            'published_by' => $admin->getKey(),
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->get('/dashboard?event='.$event->getKey())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.schedules', 2)
                ->where('summary.published_schedules', 1)
                ->where('summary.schedule_draft_changes', 1));
    }

    public function test_judge_dashboard_contains_only_the_judges_event_and_exact_assignment(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'extemporaneous-speaking')
            ->firstOrFail()->divisions()->firstOrFail();
        $judge = User::factory()->create();
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        (new ConfigureJudgingPanel)->handle($admin, $contest, [$judge]);

        $this->actingAs($judge)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('capabilities.global_admin', false)
                ->where('event.roles.0', 'judge')
                ->has('events', 1)
                ->has('work_queue', 7)
                ->where('work_queue.0.scope', 'entry_scorecard')
                ->has('programme', 0)
                ->has('people', 0));
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event];
    }
}
