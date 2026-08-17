<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\LockJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Enums\EventRole;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgingPanelPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_judged_division_is_prepared_once_with_participating_entries(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $division->entries()->first()->update(['status' => 'withdrawn']);

        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $sameContest = (new PrepareJudgedContest)->handle($admin, $division);

        $this->assertTrue($contest->is($sameContest));
        $this->assertSame(1, Contest::query()->whereBelongsTo($division, 'division')->count());
        $this->assertSame(6, $contest->entries()->count());
        $this->assertSame($division->governingRuleVersion()->value('id'), $contest->competition_rule_version_id);
    }

    public function test_blocked_source_cannot_be_prepared_for_scoring(): void
    {
        [$admin, $event, $division] = $this->context('essay-writing');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('blocked');

        (new PrepareJudgedContest)->handle($admin, $division);
    }

    public function test_panel_configuration_creates_one_prebound_scorecard_and_assignment_per_entry_and_judge(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judges = $this->judges($admin, $event, 2);

        (new ConfigureJudgingPanel)->handle($admin, $contest, $judges);
        (new ConfigureJudgingPanel)->handle($admin, $contest, $judges);

        $this->assertSame(14, $contest->scorecards()->count());
        $this->assertSame(14, $event->assignments()->where('scope_type', 'entry_scorecard')->whereNull('revoked_at')->count());
        $this->assertSame(
            14,
            $contest->scorecards()->get(['entry_id', 'judge_id'])
                ->unique(fn ($scorecard): string => $scorecard->entry_id.':'.$scorecard->judge_id)
                ->count(),
        );
    }

    public function test_unused_removed_judge_scorecards_are_removed_but_scoring_history_blocks_panel_change(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        [$judgeA, $judgeB] = $this->judges($admin, $event, 2);
        $action = new ConfigureJudgingPanel;
        $action->handle($admin, $contest, [$judgeA, $judgeB]);

        $action->handle($admin, $contest, [$judgeA]);
        $this->assertSame(7, $contest->scorecards()->count());
        $this->assertSame(0, $contest->scorecards()->where('judge_id', $judgeB->getKey())->count());

        $scorecard = $contest->scorecards()->where('judge_id', $judgeA->getKey())->firstOrFail();
        $scorecard->update(['revision' => 1]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('audited correction');
        $action->handle($admin, $contest, []);
    }

    public function test_a_judge_cannot_be_added_after_scoring_evidence_exists(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        [$judgeA, $judgeB] = $this->judges($admin, $event, 2);
        $action = new ConfigureJudgingPanel;
        $action->handle($admin, $contest, [$judgeA]);
        $contest->scorecards()->firstOrFail()->update(['revision' => 1]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('audited correction');

        $action->handle($admin, $contest, [$judgeA, $judgeB]);
    }

    public function test_repreparation_removes_an_ineligible_entry_without_scoring_evidence(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $staleEntry = $contest->entries()->firstOrFail()->entry;
        $before = $contest->entries()->count();
        $staleEntry->update(['status' => 'withdrawn']);

        (new PrepareJudgedContest)->handle($admin, $division);

        $this->assertSame($before - 1, $contest->entries()->count());
        $this->assertDatabaseMissing('contest_entries', [
            'contest_id' => $contest->getKey(),
            'entry_id' => $staleEntry->getKey(),
        ]);
    }

    public function test_panel_lock_requires_a_complete_consistent_panel_and_is_idempotent(): void
    {
        [$admin, $event, $division] = $this->context('pop-solo');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judges = $this->judges($admin, $event, 2);
        (new ConfigureJudgingPanel)->handle($admin, $contest, $judges);
        $contest->ruleVersion->confirmAggregation(
            $admin,
            'average',
            'Authorized event committee decision',
            'The committee approved average aggregation.',
        );

        $locked = (new LockJudgingPanel)->handle($admin, $contest);
        $same = (new LockJudgingPanel)->handle($admin, $locked);

        $this->assertNotNull($same->judging_panel_locked_at);
        $this->assertSame($admin->getKey(), $same->judging_panel_locked_by);
        $this->assertSame('frozen', $same->ruleVersion->lifecycleState()->value);
    }

    public function test_panel_lock_requires_source_deduction_calculation_authorization(): void
    {
        [$admin, $event, $division] = $this->context('story-telling');
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        (new ConfigureJudgingPanel)->handle($admin, $contest, $this->judges($admin, $event, 1));
        $contest->ruleVersion->confirmAggregation(
            $admin,
            'average',
            'Authorized event committee decision',
            'The committee approved average aggregation.',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('deduction calculation');

        (new LockJudgingPanel)->handle($admin, $contest);
    }

    /** @return array{0: User, 1: Event, 2: Division} */
    private function context(string $competitionSlug): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', $competitionSlug)
            ->firstOrFail()
            ->divisions()
            ->firstOrFail();

        return [$admin, $event->fresh(), $division];
    }

    /** @return list<User> */
    private function judges(User $admin, Event $event, int $count): array
    {
        return User::factory()->count($count)->create()->each(
            fn (User $judge) => (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge),
        )->all();
    }
}
