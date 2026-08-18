<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\LockJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Task3OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_judge_landing_groups_only_exact_assigned_scorecards_and_exposes_schedule_context(): void
    {
        [$admin, $event, $contest, $judge] = $this->judgedContext();
        $secondJudge = User::factory()->create(['email' => 'peer-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $secondJudge, EventRole::Judge);
        (new ConfigureJudgingPanel)->handle($admin, $contest, [$judge, $secondJudge]);
        $contest->ruleVersion->confirmAggregation(
            $admin,
            'average',
            'Authorized event committee decision',
            'The committee approved average aggregation.',
        );
        (new LockJudgingPanel)->handle($admin, $contest);

        $venue = Venue::firstOrCreate(
            ['event_id' => $event->getKey(), 'name' => 'CSPC Auditorium'],
            ['is_active' => true],
        );
        Schedule::create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $contest->competition_division_id,
            'contest_id' => $contest->getKey(),
            'venue_id' => $venue->getKey(),
            'title' => 'Pop Solo finals',
            'starts_at' => now()->addDay()->setTime(13, 0),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($judge)->get(route('judge.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Judge/Index')
            ->where('contests.0.name', 'Pop Solo')
            ->where('contests.0.schedule.venue.name', 'CSPC Auditorium')
            ->where('contests.0.scorecard_count', $contest->entries()->count())
            ->where('contests.0.scorecards', function ($scorecards): bool {
                return count($scorecards) === 7
                    && collect($scorecards)->every(fn (array $scorecard): bool => ! array_key_exists('judge_id', $scorecard))
                    && collect($scorecards)->every(fn (array $scorecard): bool => ! array_key_exists('calculated_total', $scorecard));
            })
            ->missing('contests.0.scorecards.0.peer_scores')
            ->where('contests.0.scorecards.0.id', (string) $contest->scorecards()->where('judge_id', $judge->getKey())->firstOrFail()->getKey())
            ->where('contests.0.scorecards.0.href', route('judge.scorecards.show', $contest->scorecards()->where('judge_id', $judge->getKey())->firstOrFail()))
        );
    }

    public function test_blocked_judge_scorecard_dto_keeps_its_id_and_has_no_href(): void
    {
        [, , $contest, $judge] = $this->judgedContext();
        $scorecard = $contest->scorecards()->where('judge_id', $judge->getKey())->orderBy('entry_id')->firstOrFail();

        $this->actingAs($judge)
            ->get(route('judge.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contests.0.scorecards.0.id', (string) $scorecard->getKey())
                ->where('contests.0.scorecards.0.status', 'blocked')
                ->where('contests.0.scorecards.0.href', null)
            );
    }

    public function test_tabulator_landing_separates_judged_and_objective_work_and_surfaces_readiness(): void
    {
        [$admin, $event, $contest, $judge] = $this->judgedContext();
        $tabulator = User::factory()->create(['email' => 'tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new GrantScoringAssignment)->handle($admin, $event, $tabulator, ScoringAssignmentScope::Contest, $contest);

        $response = $this->actingAs($tabulator)->get(route('tabulator.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tabulator/Index')
            ->has('judged', 1)
            ->has('objective', 0)
            ->where('judged.0.name', 'Pop Solo')
            ->where('judged.0.completion.expected', 7)
            ->where('judged.0.readiness.ready', false)
            ->where('judged.0.readiness.next_blocker', 'Judging panel must be locked before tabulation.')
            ->missing('judged.0.scoring_configuration')
            ->where('judged.0.href', route('tabulator.contests.show', $contest))
        );
    }

    public function test_criteria_based_contest_rejects_objective_command_processing(): void
    {
        [$admin, $event, $contest] = $this->judgedContext();
        $tabulator = User::factory()->create(['email' => 'command-tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new GrantScoringAssignment)->handle($admin, $event, $tabulator, ScoringAssignmentScope::Contest, $contest);

        $this->actingAs($tabulator)->postJson(route('tabulator.contests.command', $contest), [
            'command_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'schema_version' => 1,
            'command_type' => 'record_live_score',
            'base_revision' => 0,
            'payload' => ['home' => 1, 'away' => 0],
        ])->assertStatus(422)->assertJson([
            'error_code' => 'criteria_based_contests_require_judged_tabulation',
        ]);
    }

    public function test_admin_staff_workspace_has_url_sections_and_blocked_scoring_readiness(): void
    {
        [$admin, $event] = $this->programme();

        $this->actingAs($admin)
            ->get(route('admin.staff.index', ['event' => $event, 'section' => 'readiness']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Staff/Index')
                ->where('section', 'readiness')
                ->has('readiness')
                ->where('readiness', function ($readiness): bool {
                    $essay = collect($readiness)->firstWhere('name', 'Essay Writing');

                    return is_array($essay)
                        && $essay['state'] === 'blocked'
                        && $essay['source']['blocker'] === 'Criteria total 95 while the source prints 100.'
                        && ! array_key_exists('scoring_configuration', $essay);
                })
                ->has('targets.competition_division')
                ->has('targets.contest')
                ->missing('targets.entry_scorecard')
            );
    }

    public function test_admin_can_prepare_configure_assign_confirm_and_lock_a_judged_contest(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')
            ->firstOrFail()->divisions()->firstOrFail();
        $judge = User::factory()->create(['email' => 'judge-'.uniqid().'@example.com']);
        $tabulator = User::factory()->create(['email' => 'tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);

        $this->assertAdminRedirect($this->actingAs($admin)->post(route('admin.staff.scoring.prepare', [$event, $division])));
        $contest = Contest::query()->whereBelongsTo($division, 'division')->sole();

        $this->assertAdminRedirect($this->actingAs($admin)->post(
            route('admin.staff.scoring.panel.store', [$event, $contest]),
            ['judge_ids' => [$judge->getKey()]],
        ));
        $this->assertAdminRedirect($this->actingAs($admin)->post(
            route('admin.staff.scoring.tabulator.store', [$event, $contest, $tabulator]),
        ));
        $this->assertAdminRedirect($this->actingAs($admin)->post(
            route('admin.staff.scoring.aggregation.confirm', [$event, $contest]),
            ['method' => 'average', 'reference' => 'Committee minute 18', 'reason' => 'Authorized for this event.'],
        ));
        $this->assertAdminRedirect($this->actingAs($admin)->post(route('admin.staff.scoring.panel.lock', [$event, $contest])));

        $this->assertDatabaseHas('contests', ['id' => $contest->getKey()]);
        $this->assertNotNull($contest->fresh()->judging_panel_locked_at);
        $this->assertDatabaseHas('scoring_assignments', [
            'event_id' => $event->getKey(),
            'user_id' => $tabulator->getKey(),
            'contest_id' => $contest->getKey(),
            'scope_type' => 'contest',
            'revoked_at' => null,
        ]);
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(\Database\Seeders\SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event->fresh()];
    }

    /** @return array{0: User, 1: Event, 2: Contest, 3: User} */
    private function judgedContext(): array
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')
            ->firstOrFail()->divisions()->firstOrFail();
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judge = User::factory()->create(['email' => 'judge-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        (new ConfigureJudgingPanel)->handle($admin, $contest, [$judge]);

        return [$admin, $event->fresh(), $contest->fresh(), $judge];
    }

    private function assertAdminRedirect(TestResponse $response): void
    {
        $response->assertRedirect();
    }
}
