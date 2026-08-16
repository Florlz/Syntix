<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Actions\Scoring\ApproveContestOutcome;
use App\Actions\Scoring\ApproveDivisionPlacement;
use App\Actions\Scoring\CompleteContest;
use App\Actions\Scoring\RecordLiveScore;
use App\Actions\Scoring\RejectContestResult;
use App\Actions\Scoring\StartContest;
use App\Actions\Scoring\SubmitContestResult;
use App\Actions\Scoring\SubmitDivisionPlacement;
use App\Enums\CompetitionFormat;
use App\Enums\EventRole;
use App\Enums\OutcomeType;
use App\Enums\ParticipantMode;
use App\Enums\RoundingMode;
use App\Enums\ScoringAssignmentScope;
use App\Enums\ScoringFamily;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Entry;
use App\Models\EventDelegation;
use App\Models\PlacementPointRule;
use App\Models\PlacementPointTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_contest_approval_does_not_create_championship_points_but_final_placement_does(): void
    {
        $context = $this->context();

        $started = (new StartContest)->handle($context['tabulator'], $context['contest']);
        $scored = (new RecordLiveScore)->handle($context['tabulator'], $started, [
            'home' => 10,
            'away' => 8,
        ], 1);
        $completed = (new CompleteContest)->handle($context['tabulator'], $scored, [
            'outcome_type' => OutcomeType::Played->value,
            'winner_entry_id' => $context['entry']->getKey(),
            'home' => 10,
            'away' => 8,
        ], 2);
        $submission = (new SubmitContestResult)->handle($context['tabulator'], $completed);
        $outcome = (new ApproveContestOutcome)->handle($context['admin'], $submission);

        $this->assertSame('approved', $outcome->state->value);
        $this->assertDatabaseCount('score_ledger_entries', 0);

        $placement = (new SubmitDivisionPlacement)->handle(
            $context['admin'],
            $context['division'],
            [[
                'entry_id' => $context['entry']->getKey(),
                'rank' => 1,
                'placement_key' => 'champion',
            ]],
        );

        (new ApproveDivisionPlacement)->handle($context['admin'], $placement);

        $this->assertDatabaseHas('score_ledger_entries', [
            'division_placement_id' => $placement->getKey(),
            'event_delegation_id' => $context['delegation']->getKey(),
            'entry_type' => 'award',
            'amount' => '25.0000',
        ]);
        $this->assertSame('25', (string) $context['delegation']->fresh()->ledgerEntries()->sum('amount'));
    }

    public function test_result_and_final_placement_submissions_notify_the_global_admin_once_per_transition(): void
    {
        $context = $this->context();

        $started = (new StartContest)->handle($context['tabulator'], $context['contest']);
        $completed = (new CompleteContest)->handle($context['tabulator'], $started, [
            'outcome_type' => OutcomeType::Played->value,
            'winner_entry_id' => $context['entry']->getKey(),
            'home' => 10,
            'away' => 8,
        ], 1);

        (new SubmitContestResult)->handle($context['tabulator'], $completed);
        $this->assertSame(1, $context['admin']->notifications()->where('type', \App\Notifications\AdminActivityNotification::class)->count());

        // Replaying an already-submitted transition must not duplicate the alert.
        (new SubmitContestResult)->handle($context['tabulator'], $completed);
        $this->assertSame(1, $context['admin']->notifications()->where('type', \App\Notifications\AdminActivityNotification::class)->count());

        (new SubmitDivisionPlacement)->handle(
            $context['admin'],
            $context['division'],
            [[
                'entry_id' => $context['entry']->getKey(),
                'rank' => 1,
                'placement_key' => 'champion',
            ]],
        );

        $this->assertSame(2, $context['admin']->notifications()->where('type', \App\Notifications\AdminActivityNotification::class)->count());
        $this->assertEqualsCanonicalizing(
            ['approval_result', 'approval_placement'],
            $context['admin']->notifications()->get()->map(fn ($notification): string => $notification->data['kind'])->all(),
        );
    }

    public function test_returning_a_result_for_correction_reopens_the_contest_for_resubmission(): void
    {
        $context = $this->context();
        $started = (new StartContest)->handle($context['tabulator'], $context['contest']);
        $scored = (new RecordLiveScore)->handle($context['tabulator'], $started, [
            'home' => 10,
            'away' => 8,
        ], 1);
        $completed = (new CompleteContest)->handle($context['tabulator'], $scored, [
            'outcome_type' => OutcomeType::Played->value,
            'winner_entry_id' => $context['entry']->getKey(),
            'home' => 10,
            'away' => 8,
        ], 2);
        $submission = (new SubmitContestResult)->handle($context['tabulator'], $completed);

        (new RejectContestResult)->handle($context['admin'], $submission, 'The final score needs correction.');

        $reopened = $context['contest']->fresh();
        $this->assertSame('live', $reopened->state->value);
        $this->assertNull($reopened->completed_at);

        $recompleted = (new CompleteContest)->handle($context['tabulator'], $reopened, [
            'outcome_type' => OutcomeType::Played->value,
            'winner_entry_id' => $context['entry']->getKey(),
            'home' => 11,
            'away' => 9,
        ], $reopened->revision);
        $replacement = (new SubmitContestResult)->handle($context['tabulator'], $recompleted);

        $this->assertNotSame($submission->getKey(), $replacement->getKey());
        $this->assertSame('submitted', $replacement->state->value);
        $this->assertSame(11, $replacement->payload['home']);
    }

    public function test_activation_blocks_criteria_weights_that_do_not_total_one_hundred_percent(): void
    {
        $context = $this->context(criteria: true);
        $context['version']->update([
            'scoring_family' => ScoringFamily::CriteriaBased,
            'criteria_calculation_mode' => 'percentage_weight',
        ]);

        $context['version']->criteria()->createMany([
            [
                'name' => 'Criterion A',
                'source_label' => 'A',
                'display_order' => 1,
                'number_meaning' => 'percentage_weight',
                'weight' => '50.0000',
            ],
            [
                'name' => 'Criterion B',
                'source_label' => 'B',
                'display_order' => 2,
                'number_meaning' => 'percentage_weight',
                'weight' => '45.0000',
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Criteria weights must total exactly 100 percent.');

        (new ActivateRuleVersion)->handle($context['admin'], $context['version']);
    }

    /**
     * @return array{admin: User, tabulator: User, division: Division, contest: Contest, entry: Entry, delegation: EventDelegation, version: CompetitionRuleVersion}
     */
    private function context(bool $criteria = false): array
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);
        $tabulator = User::factory()->create(['email' => 'tabulator-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);

        $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
        $opponentDelegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Basketball']);
        $division = Division::factory()->create([
            'competition_id' => $competition->getKey(),
            'name' => 'Men',
        ]);

        $template = PlacementPointTemplate::create([
            'event_id' => $event->getKey(),
            'code' => 'major-'.uniqid(),
            'name' => 'Major',
            'version' => 1,
            'is_signed_off' => false,
        ]);
        foreach ([
            ['placement_key' => 'champion', 'points' => '25.0000'],
            ['placement_key' => 'first_runner_up', 'points' => '20.0000'],
            ['placement_key' => 'second_runner_up', 'points' => '15.0000'],
            ['placement_key' => 'participation', 'points' => '5.0000', 'is_participation' => true],
        ] as $rule) {
            PlacementPointRule::create([
                'placement_point_template_id' => $template->getKey(),
                ...$rule,
            ]);
        }
        $template->update(['is_signed_off' => true]);

        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'placement_point_template_id' => $template->getKey(),
            'version' => 1,
            'scoring_family' => $criteria ? ScoringFamily::CriteriaBased : ScoringFamily::Objective,
            'format' => CompetitionFormat::SingleElimination,
            'participant_mode' => ParticipantMode::Team,
            'input_scale' => 0,
            'calculation_scale' => 0,
            'display_scale' => 0,
            'rounding_mode' => RoundingMode::None->value,
            'rounding_stage' => 'final',
            'tie_breaker_configuration' => ['mode' => 'manual_resolution_required'],
            'participation_configuration' => ['policy' => 'institutional'],
            'publication_configuration' => ['live' => true],
            'approval_configuration' => ['admin_required' => true],
            'scoring_configuration' => ['outcome_profile' => 'team_total'],
            'source_status' => 'verified',
            'created_by' => $admin->getKey(),
        ]);

        if (! $criteria) {
            (new ActivateRuleVersion)->handle($admin, $version);
        }

        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $entry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'CSPC Men',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $contest->entries()->create(['entry_id' => $entry->getKey(), 'slot' => 1]);
        $opponent = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $opponentDelegation->getKey(),
            'name' => 'Opponent Men',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $contest->entries()->create(['entry_id' => $opponent->getKey(), 'slot' => 2]);
        (new GrantScoringAssignment)->handle(
            $admin,
            $event,
            $tabulator,
            ScoringAssignmentScope::CompetitionDivision,
            $division,
        );

        return compact('admin', 'tabulator', 'division', 'contest', 'entry', 'delegation', 'version');
    }
}
