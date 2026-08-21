<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Identity\DisableUser;
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

    public function test_admin_staff_coverage_groups_judge_scorecards_and_marks_missing_role_coverage(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')
            ->firstOrFail()->divisions()->firstOrFail();
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judge = User::factory()->create(['name' => 'Panel Judge', 'email' => 'panel-judge-'.uniqid().'@example.com']);
        $tabulator = User::factory()->create(['name' => 'Unassigned Tabulator', 'email' => 'unassigned-tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new ConfigureJudgingPanel)->handle($admin, $contest, [$judge]);

        $this->actingAs($admin)->get(route('admin.staff.index', [$event, 'section' => 'people']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('staff', function ($staff) use ($judge, $tabulator): bool {
                    $panelJudge = collect($staff)->firstWhere('id', (string) $judge->getKey());
                    $unassignedTabulator = collect($staff)->firstWhere('id', (string) $tabulator->getKey());
                    $panel = $panelJudge['coverage']['judging_panels'][0] ?? null;

                    return is_array($panel)
                        && count($panelJudge['coverage']['judging_panels']) === 1
                        && $panel['contest_id'] === (string) $panelJudge['judging_assignments'][0]['id']
                        && $panel['entry_count'] === 7
                        && $panel['scorecard_count'] === 7
                        && ! in_array('judge', $panelJudge['coverage']['missing_roles'], true)
                        && in_array('tabulator', $unassignedTabulator['coverage']['missing_roles'], true);
                })
            );
    }

    public function test_admin_staff_readiness_exposes_ordered_workflow_and_current_panel_members(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')
            ->firstOrFail()->divisions()->firstOrFail();
        $judge = User::factory()->create(['email' => 'workflow-judge-'.uniqid().'@example.com']);
        $tabulator = User::factory()->create(['email' => 'workflow-tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);

        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'prepare');

        $this->assertAdminRedirect($this->actingAs($admin)->post(route('admin.staff.scoring.prepare', [$event, $division])));
        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'panel');

        $contest = Contest::query()->whereBelongsTo($division, 'division')->sole();
        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'panel', []);
        $this->assertAdminRedirect($this->actingAs($admin)->post(
            route('admin.staff.scoring.panel.store', [$event, $contest]),
            ['judge_ids' => [$judge->getKey()]],
        ));
        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'aggregation', [(string) $judge->getKey()]);

        $this->assertAdminRedirect($this->actingAs($admin)->post(
            route('admin.staff.scoring.aggregation.confirm', [$event, $contest]),
            ['method' => 'average', 'reference' => 'Minute 18', 'reason' => 'The committee approved the method.'],
        ));
        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'tabulator');

        $this->assertAdminRedirect($this->actingAs($admin)->post(route('admin.staff.scoring.tabulator.store', [$event, $contest, $tabulator])));
        $this->assertReadinessAction($admin, $event, 'Pop Solo', 'lock');

        $this->assertAdminRedirect($this->actingAs($admin)->post(route('admin.staff.scoring.panel.lock', [$event, $contest])));
        $this->actingAs($admin)->get(route('admin.staff.index', [$event, 'section' => 'readiness']))
            ->assertInertia(fn (Assert $page) => $page->where('readiness', function ($readiness): bool {
                $popSolo = collect($readiness)->firstWhere('name', 'Pop Solo');

                return ($popSolo['next_action_key'] ?? null) !== 'lock';
            }));
    }

    public function test_admin_staff_readiness_requires_a_tabulator_before_locking_when_none_are_active(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')
            ->firstOrFail()->divisions()->firstOrFail();
        $judge = User::factory()->create(['email' => 'no-tabulator-judge-'.uniqid().'@example.com']);
        $tabulator = User::factory()->create(['email' => 'disabled-tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);

        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        (new ConfigureJudgingPanel)->handle($admin, $contest, [$judge]);
        (new GrantScoringAssignment)->handle($admin, $event, $tabulator, ScoringAssignmentScope::Contest, $contest);
        $contest->ruleVersion->confirmAggregation(
            $admin,
            'average',
            'Minute 18',
            'The committee approved the method.',
        );
        (new DisableUser)->handle($admin, $tabulator, 'Unavailable for scoring operations.', $event);

        $this->actingAs($admin)->get(route('admin.staff.index', [$event, 'section' => 'readiness']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('readiness', function ($readiness): bool {
                $popSolo = collect($readiness)->firstWhere('name', 'Pop Solo');

                return $popSolo['next_action_key'] === 'tabulator'
                    && $popSolo['tabulator_available'] === false
                    && $popSolo['counts']['tabulators'] === 0
                    && $popSolo['next_action_key'] !== 'lock';
            }));
    }

    public function test_admin_staff_invitation_reissue_exposes_print_card_metadata_without_persisting_token(): void
    {
        [$admin, $event] = $this->programme();
        $judge = User::factory()->create(['name' => 'Reissued Judge', 'email' => 'reissued-judge-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);

        $response = $this->actingAs($admin)->post(route('admin.staff.invitations.reissue', [$event, $judge]));

        $response->assertRedirect();
        $this->assertNotNull(session('setup_url'));
        $this->assertSame('Reissued Judge', session('setup_invitation.name'));
        $this->assertSame('Judge', session('setup_invitation.role_label'));
        $this->assertNotNull(session('setup_invitation.expires_at'));
        $this->assertDatabaseMissing('user_invitations', ['token_hash' => session('setup_url')]);
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

    /** @param list<string> $judgeIds */
    private function assertReadinessAction(User $admin, Event $event, string $name, ?string $expected, ?array $judgeIds = null): void
    {
        $this->actingAs($admin)->get(route('admin.staff.index', [$event, 'section' => 'readiness']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('readiness', function ($readiness) use ($name, $expected, $judgeIds): bool {
                $item = collect($readiness)->firstWhere('name', $name);

                return ($item['next_action_key'] ?? null) === $expected
                    && ($judgeIds === null || $item['current_judge_ids'] === $judgeIds);
            }));
    }
}
