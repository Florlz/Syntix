<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Actions\Scoring\SaveJudgeScorecard;
use App\Actions\Scoring\SubmitJudgeScorecard;
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
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\PlacementPointRule;
use App\Models\PlacementPointTemplate;
use App\Models\ScoringCriterion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgedScorecardTest extends TestCase
{
    use RefreshDatabase;

    public function test_judge_can_save_an_exact_assigned_scorecard_with_fixed_precision_total(): void
    {
        $context = $this->context();

        $scorecard = (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
            ['criterion_id' => $context['criteria'][1]->getKey(), 'raw_value' => '90'],
        ], 0);

        $this->assertSame('48.0000', (string) $scorecard->values()->where('scoring_criterion_id', $context['criteria'][0]->getKey())->value('weighted_value'));
        $this->assertSame('84.0000', (string) $scorecard->calculated_total);

        $submitted = (new SubmitJudgeScorecard)->handle($context['judge'], $scorecard);

        $this->assertSame('submitted', $submitted->scorecardState()->value);
        $this->assertSame(2, (int) $submitted->revision);
    }

    public function test_judge_cannot_save_before_the_panel_and_aggregation_authority_are_locked(): void
    {
        $context = $this->context(panelLocked: false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('panel and aggregation authority are locked');

        (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
        ], 0);
    }

    public function test_judge_cannot_score_an_unassigned_scorecard(): void
    {
        $context = $this->context(assign: false);

        $this->expectException(AuthorizationException::class);
        (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
        ], 0);
    }

    public function test_judge_cannot_claim_a_null_judge_scorecard_when_saving(): void
    {
        $context = $this->context(bindJudge: false);

        $this->expectException(AuthorizationException::class);

        (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
        ], 0);
    }

    public function test_judge_cannot_claim_a_null_judge_scorecard_when_submitting(): void
    {
        $context = $this->context();
        $scorecard = (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
            ['criterion_id' => $context['criteria'][1]->getKey(), 'raw_value' => '90'],
        ], 0);
        $scorecard->update(['judge_id' => null]);

        $this->expectException(AuthorizationException::class);

        (new SubmitJudgeScorecard)->handle($context['judge'], $scorecard->fresh());
    }

    public function test_a_different_assigned_judge_cannot_submit_an_existing_judges_scorecard(): void
    {
        $context = $this->context();
        (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
            ['criterion_id' => $context['criteria'][1]->getKey(), 'raw_value' => '90'],
        ], 0);

        $otherJudge = User::factory()->create(['email' => 'other-judge-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($context['admin'], $context['event'], $otherJudge, EventRole::Judge);
        (new GrantScoringAssignment)->handle(
            $context['admin'],
            $context['event'],
            $otherJudge,
            ScoringAssignmentScope::EntryScorecard,
            $context['scorecard'],
        );

        $this->expectException(AuthorizationException::class);
        (new SubmitJudgeScorecard)->handle($otherJudge, $context['scorecard']);
    }

    public function test_judge_can_use_the_scorecard_workbench_for_an_exact_assignment(): void
    {
        $context = $this->context();

        $this->actingAs($context['judge'])
            ->get(route('judge.scorecards.show', $context['scorecard']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Judge/Scorecard')
                ->where('scorecard.id', (string) $context['scorecard']->getKey())
                ->has('scorecard.criteria', 2)
                ->missing('scorecard.judge'));

        $this->patch(route('judge.scorecards.update', $context['scorecard']), [
            'expected_revision' => 0,
            'values' => [
                ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80', 'deduction' => '0'],
                ['criterion_id' => $context['criteria'][1]->getKey(), 'raw_value' => '90', 'deduction' => '0'],
            ],
        ])->assertRedirect();

        $this->post(route('judge.scorecards.submit', $context['scorecard']))->assertRedirect();
        $this->assertDatabaseHas('judge_scorecards', [
            'id' => $context['scorecard']->getKey(),
            'state' => 'submitted',
            'revision' => 2,
        ]);
    }

    public function test_sparse_draft_save_persists_the_submitted_criterion_when_another_raw_value_is_omitted(): void
    {
        $context = $this->context();

        $this->actingAs($context['judge'])
            ->patch(route('judge.scorecards.update', $context['scorecard']), [
                'expected_revision' => 0,
                'values' => [
                    [
                        'criterion_id' => $context['criteria'][0]->getKey(),
                        'raw_value' => '80',
                        'deduction' => 0,
                        'notes' => 'Focused draft note',
                    ],
                    [
                        'criterion_id' => $context['criteria'][1]->getKey(),
                        'deduction' => 0,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $savedValues = $context['scorecard']->fresh('values')->values;

        $this->assertCount(1, $savedValues);
        $this->assertSame($context['criteria'][0]->getKey(), $savedValues->sole()->scoring_criterion_id);
        $this->assertSame('80.0000', $savedValues->sole()->raw_value);
        $this->assertSame('Focused draft note', $savedValues->sole()->notes);
    }

    public function test_submission_still_rejects_a_missing_required_persisted_criterion(): void
    {
        $context = $this->context();

        $scorecard = (new SaveJudgeScorecard)->handle($context['judge'], $context['scorecard'], [
            ['criterion_id' => $context['criteria'][0]->getKey(), 'raw_value' => '80'],
        ], 0);

        $this->actingAs($context['judge'])
            ->post(route('judge.scorecards.submit', $scorecard))
            ->assertSessionHasErrors('scorecard');

        $this->assertSame('draft', $scorecard->fresh()->scorecardState()->value);
    }

    public function test_judge_cannot_view_peer_scorecards_without_the_exact_assignment(): void
    {
        $context = $this->context();
        $peerScorecard = EntryScorecard::create([
            'contest_id' => $context['scorecard']->contest_id,
            'entry_id' => $context['scorecard']->entry_id,
            'competition_rule_version_id' => $context['scorecard']->competition_rule_version_id,
            'entry_reference' => 'peer-entry',
            'state' => 'draft',
            'revision' => 0,
        ]);

        $this->actingAs($context['judge'])
            ->get(route('judge.scorecards.show', $peerScorecard))
            ->assertForbidden();
    }

    public function test_judge_cannot_view_an_exactly_assigned_scorecard_bound_to_another_judge(): void
    {
        $context = $this->context();
        $otherJudge = User::factory()->create(['email' => 'bound-peer-'.uniqid().'@example.com']);
        $context['scorecard']->update(['judge_id' => $otherJudge->getKey()]);

        $this->actingAs($context['judge'])
            ->get(route('judge.scorecards.show', $context['scorecard']))
            ->assertForbidden();
    }

    /** @return array{admin: User, event: Event, judge: User, scorecard: EntryScorecard, criteria: list<ScoringCriterion>} */
    private function context(bool $assign = true, bool $bindJudge = true, bool $panelLocked = true): array
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);
        $judge = User::factory()->create(['email' => 'judge-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);
        $template = PlacementPointTemplate::create([
            'event_id' => $event->getKey(),
            'code' => 'individual-'.uniqid(),
            'name' => 'Individual',
            'version' => 1,
            'is_signed_off' => false,
        ]);
        PlacementPointRule::create([
            'placement_point_template_id' => $template->getKey(),
            'placement_key' => 'champion',
            'points' => '5.0000',
        ]);
        $template->update(['is_signed_off' => true]);
        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'placement_point_template_id' => $template->getKey(),
            'version' => 1,
            'scoring_family' => ScoringFamily::CriteriaBased,
            'format' => CompetitionFormat::CriteriaBased,
            'participant_mode' => ParticipantMode::Individual,
            'criteria_calculation_mode' => 'percentage_weight',
            'judge_aggregation_method' => 'average',
            'verified_scorecard_total' => '100.0000',
            'input_scale' => 0,
            'calculation_scale' => 2,
            'display_scale' => 2,
            'rounding_mode' => 'none',
            'rounding_stage' => 'final',
            'tie_breaker_configuration' => ['mode' => 'manual_resolution_required'],
            'participation_configuration' => ['policy' => 'institutional'],
            'publication_configuration' => ['live' => false],
            'approval_configuration' => ['admin_required' => true],
            'source_status' => 'verified',
            'created_by' => $admin->getKey(),
        ]);
        $criteria = [
            ScoringCriterion::create([
                'competition_rule_version_id' => $version->getKey(),
                'name' => 'Craft',
                'source_label' => 'Craft',
                'display_order' => 1,
                'number_meaning' => 'percentage_weight',
                'weight' => '60.0000',
                'raw_minimum' => '0.0000',
                'raw_maximum' => '100.0000',
            ]),
            ScoringCriterion::create([
                'competition_rule_version_id' => $version->getKey(),
                'name' => 'Impact',
                'source_label' => 'Impact',
                'display_order' => 2,
                'number_meaning' => 'percentage_weight',
                'weight' => '40.0000',
                'raw_minimum' => '0.0000',
                'raw_maximum' => '100.0000',
            ]),
        ];
        (new ActivateRuleVersion)->handle($admin, $version);
        $version->confirmAggregation(
            $admin,
            'average',
            'Authorized test authority',
            'The test panel approved average aggregation.',
        );
        $contest = Contest::factory()->create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $version->getKey(),
            'judging_panel_locked_at' => $panelLocked ? now() : null,
            'judging_panel_locked_by' => $panelLocked ? $admin->getKey() : null,
        ]);
        $entry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'Solo entry',
            'entry_mode' => ParticipantMode::Individual,
            'status' => 'active',
        ]);
        $scorecard = EntryScorecard::create([
            'contest_id' => $contest->getKey(),
            'entry_id' => $entry->getKey(),
            'judge_id' => $bindJudge ? $judge->getKey() : null,
            'competition_rule_version_id' => $version->getKey(),
            'entry_reference' => 'solo-entry',
            'state' => 'draft',
            'revision' => 0,
        ]);

        if ($assign) {
            (new GrantScoringAssignment)->handle(
                $admin,
                $event,
                $judge,
                ScoringAssignmentScope::EntryScorecard,
                $scorecard,
            );
        }

        return compact('admin', 'event', 'judge', 'scorecard', 'criteria');
    }
}
