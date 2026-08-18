<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ApproveContestOutcome;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\FinalizeJudgedContest;
use App\Actions\Scoring\LockJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Actions\Scoring\RejectContestResult;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Competition;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgedResultLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabulator_finalizes_and_submits_full_judged_snapshot(): void
    {
        $context = $this->context();
        $submission = (new FinalizeJudgedContest)->handle($context['tabulator'], $context['contest']);
        $payload = $submission->payload;

        $this->assertSame('submitted', $submission->submissionState()->value);
        $this->assertSame('completed', $context['contest']->fresh()->state->value);
        $this->assertSame('judged', $payload['scoring_mode']);
        $this->assertSame('played', $payload['outcome_type']);
        $this->assertSame('average', $payload['aggregation_method']);
        $this->assertCount(7, $payload['ranked_entries']);
        $this->assertNotEmpty($payload['ranked_entries'][0]['scorecards']);
        $this->assertSame($payload['ranked_entries'][0]['entry_id'], $payload['winner_entry_id']);
    }

    public function test_admin_approval_marks_judge_scorecards_approved_and_submits_judged_placement(): void
    {
        $context = $this->context();
        $submission = (new FinalizeJudgedContest)->handle($context['tabulator'], $context['contest']);
        $outcome = (new ApproveContestOutcome)->handle($context['admin'], $submission, 'Evidence verified.');

        $this->assertSame('judged', $outcome->payload['scoring_mode']);
        $this->assertSame(14, $context['contest']->scorecards()->where('state', 'approved')->count());
        $placement = $context['contest']->division->placements()->with('items')->sole();
        $this->assertSame('submitted', $placement->state->value);
        $this->assertCount(7, $placement->items);
        $this->assertDatabaseMissing('score_ledger_entries', ['division_placement_id' => $placement->getKey()]);
    }

    public function test_missing_judge_scorecard_blocks_finalization_without_mutating_contest(): void
    {
        $context = $this->context();
        $context['contest']->scorecards()->firstOrFail()->update(['state' => 'draft', 'calculated_total' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('missing_scorecards');

        (new FinalizeJudgedContest)->handle($context['tabulator'], $context['contest']);
    }

    public function test_admin_can_return_only_affected_judge_scorecards_for_correction(): void
    {
        $context = $this->context();
        $submission = (new FinalizeJudgedContest)->handle($context['tabulator'], $context['contest']);
        $affected = $context['contest']->scorecards()->firstOrFail();
        $untouched = $context['contest']->scorecards()->whereKeyNot($affected->getKey())->firstOrFail();

        (new RejectContestResult)->handle(
            $context['admin'], $submission, 'Judge A must correct the first criterion.', [$affected->getKey()],
        );

        $this->assertSame('rejected', $affected->fresh()->state->value);
        $this->assertSame('Judge A must correct the first criterion.', $affected->fresh()->rejection_reason);
        $this->assertSame('submitted', $untouched->fresh()->state->value);
        $this->assertSame('live', $context['contest']->fresh()->state->value);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin', 'email' => 'admin@example.com', 'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'pop-solo')->firstOrFail()->divisions()->firstOrFail();
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judges = User::factory()->count(2)->create()->each(
            fn (User $judge) => (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge),
        )->all();
        (new ConfigureJudgingPanel)->handle($admin, $contest, $judges);
        $contest->ruleVersion->confirmAggregation($admin, 'average', 'Committee minute 18', 'Average was authorized.');
        (new LockJudgingPanel)->handle($admin, $contest);
        foreach ($contest->entries()->orderBy('slot')->get() as $index => $contestEntry) {
            foreach ($contest->scorecards()->where('entry_id', $contestEntry->entry_id)->get() as $judgeIndex => $scorecard) {
                $scorecard->update([
                    'state' => 'submitted', 'revision' => 2,
                    'calculated_total' => number_format(95 - ($index * 2) - $judgeIndex, 4, '.', ''),
                    'submitted_at' => now(),
                ]);
            }
        }
        $tabulator = User::factory()->create();
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new GrantScoringAssignment)->handle($admin, $event, $tabulator, ScoringAssignmentScope::Contest, $contest);

        return compact('admin', 'event', 'contest', 'tabulator');
    }
}
