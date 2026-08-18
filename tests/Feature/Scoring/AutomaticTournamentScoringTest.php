<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Brackets\GenerateRandomTournament;
use App\Actions\Brackets\PublishBracket;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Actions\Scoring\ApproveContestOutcome;
use App\Actions\Scoring\ApproveDivisionPlacement;
use App\Actions\Scoring\CompleteContest;
use App\Actions\Scoring\StartContest;
use App\Actions\Scoring\SubmitContestResult;
use App\Enums\CompetitionFormat;
use App\Enums\EventRole;
use App\Enums\ParticipantMode;
use App\Enums\ScoringAssignmentScope;
use App\Enums\ScoringFamily;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\OfficialContestOutcome;
use App\Models\PlacementPointTemplate;
use App\Models\User;
use App\Services\TournamentStandingCalculator;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomaticTournamentScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_win_derives_elimination_placement_before_championship_approval(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'team_total');
        $contest = $this->publishedContest($context);
        $homeId = (int) $contest->entries()->where('slot', 1)->value('entry_id');
        $awayId = (int) $contest->entries()->where('slot', 2)->value('entry_id');

        $outcome = $this->scoreAndApprove($context, $contest, [
            'outcome_type' => 'played',
            'home' => 81,
            'away' => 76,
            'winner_entry_id' => $awayId,
        ]);

        $this->assertSame($homeId, (int) $outcome->winner_entry_id);
        $this->assertSame($awayId, (int) $outcome->payload['loser_entry_id']);
        $placement = $context['division']->placements()->with('items')->firstOrFail();
        $this->assertSame('submitted', $placement->state->value);
        $this->assertSame(['champion', 'first_runner_up'], $placement->items->sortBy('rank')->pluck('placement_key')->all());
        $this->assertDatabaseCount('score_ledger_entries', 0);

        (new ApproveDivisionPlacement)->handle($context['admin'], $placement);
        $this->assertSame('45', (string) $context['event']->ledgerEntries()->sum('amount'));

        $standings = (new TournamentStandingCalculator)->forDivision($context['division']);
        $this->assertSame(1, $standings->firstWhere('entry_id', $homeId)['wins']);
        $this->assertSame(1, $standings->firstWhere('entry_id', $awayId)['losses']);
    }

    public function test_chess_draw_awards_internal_half_points_and_waits_for_tie_resolution(): void
    {
        $context = $this->context(CompetitionFormat::RoundRobin, 'chess', [
            'win_points' => 1,
            'draw_points' => 0.5,
            'loss_points' => 0,
        ]);
        $contest = $this->publishedContest($context);

        $outcome = $this->scoreAndApprove($context, $contest, [
            'outcome_type' => 'played',
            'home' => 0,
            'away' => 0,
            'result' => 'draw',
        ]);

        $this->assertNull($outcome->winner_entry_id);
        $standings = (new TournamentStandingCalculator)->forDivision($context['division']);
        $this->assertSame([0.5, 0.5], $standings->pluck('match_points')->all());
        $this->assertSame([1, 1], $standings->pluck('draws')->all());
        $this->assertSame(0, $context['division']->placements()->count());
    }

    public function test_source_specific_objective_evidence_is_preserved_with_the_official_result(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'best_of_sets');
        $contest = $this->publishedContest($context);
        $started = (new StartContest)->handle($context['tabulator'], $contest);

        $completed = (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => 'played',
            'home' => 2,
            'away' => 1,
            'evidence' => [
                'profile' => 'best_of_sets',
                'data' => ['home_scores' => [25, 21, 25], 'away_scores' => [20, 25, 18]],
            ],
        ], 1);

        $this->assertSame([25, 21, 25], $completed->result_payload['evidence']['data']['home_scores']);
        $this->assertSame([20, 25, 18], $completed->result_payload['evidence']['data']['away_scores']);
    }

    public function test_generic_or_incomplete_objective_evidence_is_rejected(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'best_of_sets');
        $contest = $this->publishedContest($context);
        $started = (new StartContest)->handle($context['tabulator'], $contest);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('matching home and away score rows');

        (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => 'played',
            'home' => 2,
            'away' => 1,
            'evidence' => [
                'profile' => 'best_of_sets',
                'data' => ['home_scores' => [25, 25], 'away_scores' => [20]],
            ],
        ], 1);
    }

    public function test_best_of_sets_declared_result_cannot_contradict_set_evidence(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'best_of_sets');
        $contest = $this->publishedContest($context);
        $started = (new StartContest)->handle($context['tabulator'], $contest);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not match the evidence');

        (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => 'played',
            'home' => 2,
            'away' => 0,
            'result' => 'away_win',
            'evidence' => [
                'profile' => 'best_of_sets',
                'data' => ['home_scores' => [25, 25], 'away_scores' => [20, 18]],
            ],
        ], 1);
    }

    public function test_team_tie_derives_the_winner_from_all_three_rubbers(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'team_tie');
        $contest = $this->publishedContest($context);
        $started = (new StartContest)->handle($context['tabulator'], $contest);

        $completed = (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => 'played',
            'home' => 2,
            'away' => 1,
            'evidence' => [
                'profile' => 'team_tie',
                'data' => ['rubbers' => ['home', 'away', 'home']],
            ],
        ], 1);

        $this->assertSame(2, $completed->result_payload['home']);
        $this->assertSame(1, $completed->result_payload['away']);
        $this->assertSame('home_win', $completed->result_payload['result']);
    }

    public function test_team_tie_declared_totals_cannot_contradict_rubber_evidence(): void
    {
        $context = $this->context(CompetitionFormat::SingleElimination, 'team_tie');
        $contest = $this->publishedContest($context);
        $started = (new StartContest)->handle($context['tabulator'], $contest);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('declared score does not match the evidence');

        (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => 'played',
            'home' => 1,
            'away' => 2,
            'evidence' => [
                'profile' => 'team_tie',
                'data' => ['rubbers' => ['home', 'away', 'home']],
            ],
        ], 1);
    }

    /** @return array<string, mixed> */
    private function context(CompetitionFormat $format, string $profile, array $configuration = []): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);
        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'placement_point_template_id' => PlacementPointTemplate::query()->where('code', 'major')->value('id'),
            'version' => 1,
            'scoring_family' => ScoringFamily::Objective,
            'format' => $format,
            'participant_mode' => ParticipantMode::Team,
            'input_scale' => 0,
            'calculation_scale' => 0,
            'display_scale' => 0,
            'rounding_mode' => 'none',
            'rounding_stage' => 'final',
            'tie_breaker_configuration' => ['mode' => 'authorized_resolution'],
            'participation_configuration' => ['policy' => 'approved_final_placement'],
            'publication_configuration' => ['live' => true],
            'approval_configuration' => ['global_admin_required' => true],
            'scoring_configuration' => ['outcome_profile' => $profile, ...$configuration],
            'source_status' => 'verified',
            'created_by' => $admin->getKey(),
        ]);
        (new ActivateRuleVersion)->handle($admin, $version);

        $entries = collect();
        foreach (['Home Department', 'Away Department'] as $name) {
            $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
            $entries->push(Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => $name,
                'entry_mode' => ParticipantMode::Team,
                'status' => 'locked',
            ]));
        }

        $tabulator = User::factory()->create();
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new GrantScoringAssignment)->handle($admin, $event, $tabulator, ScoringAssignmentScope::CompetitionDivision, $division);

        return compact('admin', 'event', 'division', 'entries', 'tabulator');
    }

    private function publishedContest(array $context): Contest
    {
        $tournament = (new GenerateRandomTournament)->handle(
            $context['admin'],
            $context['division'],
            (string) Str::uuid(),
        );
        (new PublishBracket)->handle($context['admin'], $tournament->bracketVersions()->firstOrFail());

        return $context['division']->contests()->where('state', 'scheduled')->firstOrFail();
    }

    private function scoreAndApprove(array $context, Contest $contest, array $payload): OfficialContestOutcome
    {
        $started = (new StartContest)->handle($context['tabulator'], $contest);
        $completed = (new CompleteContest)->handle($context['tabulator'], $started, $payload, 1);
        $submission = (new SubmitContestResult)->handle($context['tabulator'], $completed);

        return (new ApproveContestOutcome)->handle($context['admin'], $submission);
    }
}
