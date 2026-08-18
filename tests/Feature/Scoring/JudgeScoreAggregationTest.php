<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\LockJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Actions\Scoring\ResolveJudgedTie;
use App\Enums\EventRole;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\ScoringAdjustment;
use App\Models\User;
use App\ReadModels\JudgedTabulationReadModel;
use App\Services\JudgeScoreAggregationService;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgeScoreAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_panel_aggregates_with_decimal_precision_and_subtracts_active_adjustments_after_raw_totals(): void
    {
        $context = $this->lockedContext();
        $entry = $context['contest']->entries()->firstOrFail()->entry;
        $totals = $context['contest']->entries->values()->mapWithKeys(
            fn ($contestEntry, int $index): array => [$contestEntry->entry_id => [
                number_format(70 + $index, 4, '.', ''),
                number_format(71 + $index, 4, '.', ''),
            ]],
        )->all();
        $totals[$entry->getKey()] = ['90.1234', '90.5678'];
        $this->submitTotals($context['contest'], $totals);
        $adjustment = ScoringAdjustment::create([
            'contest_id' => $context['contest']->getKey(),
            'entry_id' => $entry->getKey(),
            'competition_rule_version_id' => $context['rule']->getKey(),
            'code' => 'performance_time',
            'label' => 'Performance time penalty',
            'source_reference' => 'Authorized source',
            'input_value' => '1',
            'input_unit' => 'point',
            'points' => '1.2500',
            'recorded_by' => $context['admin']->getKey(),
            'recorded_at' => now(),
        ]);

        $before = EntryScorecard::query()->where('entry_id', $entry->getKey())->pluck('calculated_total', 'id')->all();
        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']);

        $row = collect($result['entries'])->firstWhere('entry_id', (string) $entry->getKey());
        $this->assertTrue($result['readiness']['ready']);
        $this->assertSame('average', $result['aggregation_method']);
        $this->assertSame('90.3456', $row['aggregate_raw_total']);
        $this->assertSame('1.2500', $row['adjustment_total']);
        $this->assertSame('89.0956', $row['final_total']);
        $this->assertSame('90.1234', $row['scorecards'][0]['raw_total']);
        $this->assertSame('90.5678', $row['scorecards'][1]['raw_total']);
        $this->assertSame($before, EntryScorecard::query()->where('entry_id', $entry->getKey())->pluck('calculated_total', 'id')->all());
        $this->assertSame('1.2500', (string) $adjustment->fresh()->points);
    }

    public function test_readiness_explicitly_blocks_an_unlocked_panel_unconfirmed_method_and_missing_scorecards(): void
    {
        $unlocked = $this->preparedContext();
        $unlockedResult = (new JudgeScoreAggregationService)->aggregate($unlocked['contest']);

        $this->assertFalse($unlockedResult['readiness']['ready']);
        $this->assertContains('judging_panel_unlocked', $unlockedResult['readiness']['blocker_codes']);

        $context = $this->lockedContext(confirmAggregation: false);
        $missing = $context['contest']->scorecards()->firstOrFail();
        $missing->update(['state' => 'draft', 'calculated_total' => null]);
        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']);

        $this->assertFalse($result['readiness']['ready']);
        $this->assertContains('aggregation_confirmation_missing', $result['readiness']['blocker_codes']);
        $this->assertContains('missing_scorecards', $result['readiness']['blocker_codes']);
        $this->assertNotEmpty($result['readiness']['missing_scorecards']);
    }

    public function test_approved_scorecards_are_complete_and_comparison_scale_ties_require_authorized_resolution(): void
    {
        $context = $this->lockedContext(comparisonScale: 2);
        $entries = $context['contest']->entries()->orderBy('slot')->get();
        $totals = [];
        foreach ($entries as $index => $contestEntry) {
            $total = $index < 2 ? '88.1250' : number_format(70 + $index, 4, '.', '');
            $totals[$contestEntry->entry_id] = [$total, $total];
        }
        $this->submitTotals($context['contest'], $totals);
        $context['contest']->scorecards()->where('entry_id', $entries[0]->entry_id)->update(['state' => 'approved', 'approved_at' => now()]);

        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']);

        $this->assertFalse($result['readiness']['ready']);
        $this->assertContains('tie_resolution_required', $result['readiness']['blocker_codes']);
        $this->assertCount(1, $result['ties']);
        $this->assertSame([
            (int) $entries[0]->entry_id,
            (int) $entries[1]->entry_id,
        ], $result['ties'][0]['entry_ids']);
        $this->assertSame('88.12', $result['ties'][0]['comparison_total']);
    }

    public function test_admin_can_authorize_an_exact_tie_order_without_changing_raw_scores(): void
    {
        $context = $this->lockedContext(comparisonScale: 2);
        $entries = $context['contest']->entries()->orderBy('slot')->get();
        $totals = [];
        foreach ($entries as $index => $contestEntry) {
            $total = $index < 2 ? '88.1250' : number_format(70 + $index, 4, '.', '');
            $totals[$contestEntry->entry_id] = [$total, $total];
        }
        $this->submitTotals($context['contest'], $totals);
        $before = $context['contest']->scorecards()->pluck('calculated_total', 'id')->all();

        (new ResolveJudgedTie)->handle(
            $context['admin'],
            $context['contest'],
            [(int) $entries[0]->entry_id, (int) $entries[1]->entry_id],
            [(int) $entries[1]->entry_id, (int) $entries[0]->entry_id],
            'Authorized tie-break performance was conducted.',
            'Event committee resolution 2026-08-18',
        );

        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']);
        $first = collect($result['entries'])->firstWhere('entry_id', (string) $entries[0]->entry_id);
        $second = collect($result['entries'])->firstWhere('entry_id', (string) $entries[1]->entry_id);

        $this->assertTrue($result['readiness']['ready']);
        $this->assertSame(2, $first['rank']);
        $this->assertSame(1, $second['rank']);
        $this->assertNotNull($result['tie_resolution']);
        $this->assertSame($before, $context['contest']->scorecards()->pluck('calculated_total', 'id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'judged_tie.resolved']);
    }

    public function test_read_model_exposes_raw_judge_matrix_and_does_not_mutate_scorecards_or_adjustments(): void
    {
        $context = $this->lockedContext();
        $this->submitTotals($context['contest'], [
            $context['contest']->entries()->firstOrFail()->entry_id => ['81.0000', '83.0000'],
        ]);
        $scorecard = $context['contest']->scorecards()->firstOrFail();
        $adjustment = ScoringAdjustment::create([
            'contest_id' => $context['contest']->getKey(),
            'entry_id' => $scorecard->entry_id,
            'competition_rule_version_id' => $context['rule']->getKey(),
            'code' => 'test-adjustment',
            'label' => 'Test adjustment',
            'source_reference' => 'Test source',
            'input_value' => '1',
            'input_unit' => 'point',
            'points' => '2.0000',
            'recorded_by' => $context['admin']->getKey(),
            'recorded_at' => now(),
        ]);
        $scorecardSnapshot = $scorecard->fresh()->only(['state', 'revision', 'calculated_total']);
        $adjustmentSnapshot = $adjustment->fresh()->only(['points', 'voided_at']);

        $matrix = (new JudgedTabulationReadModel)->forContest($context['contest']);

        $this->assertSame($scorecardSnapshot, $scorecard->fresh()->only(['state', 'revision', 'calculated_total']));
        $this->assertSame($adjustmentSnapshot, $adjustment->fresh()->only(['points', 'voided_at']));
        $this->assertSame(2, count($matrix['entries'][0]['scorecards']));
        $this->assertSame('81.0000', $matrix['entries'][0]['scorecards'][0]['raw_total']);
        $this->assertSame('83.0000', $matrix['entries'][0]['scorecards'][1]['raw_total']);
        $this->assertSame('2.0000', $matrix['entries'][0]['adjustments'][0]['points']);
    }

    public function test_read_model_preserves_voided_adjustment_history_and_exposes_operational_state(): void
    {
        $context = $this->lockedContext();
        $entries = $context['contest']->entries()->orderBy('slot')->get();
        $totals = [];
        foreach ($entries as $index => $contestEntry) {
            $totals[$contestEntry->entry_id] = [
                number_format(90 - $index, 4, '.', ''),
                number_format(89 - $index, 4, '.', ''),
            ];
        }
        $this->submitTotals($context['contest'], $totals);
        $adjustment = ScoringAdjustment::create([
            'contest_id' => $context['contest']->getKey(),
            'entry_id' => $entries->first()->entry_id,
            'competition_rule_version_id' => $context['rule']->getKey(),
            'code' => 'test-adjustment',
            'label' => 'Test adjustment',
            'source_reference' => 'Test source',
            'input_value' => '1',
            'input_unit' => 'point',
            'points' => '2.0000',
            'recorded_by' => $context['admin']->getKey(),
            'recorded_at' => now(),
        ]);
        $adjustment->update([
            'voided_by' => $context['admin']->getKey(),
            'voided_at' => now(),
            'void_reason' => 'Timing sheet corrected',
        ]);

        $matrix = (new JudgedTabulationReadModel)->forContest($context['contest']);
        $row = collect($matrix['entries'])->firstWhere('entry_id', (string) $entries->first()->entry_id);

        $this->assertSame([], $row['adjustments']);
        $this->assertSame('voided', $row['adjustment_history'][0]['status']);
        $this->assertSame('Timing sheet corrected', $row['adjustment_history'][0]['void_reason']);
        $this->assertSame('ready', $matrix['operational_state']);
        $this->assertNull($matrix['submission']);
    }

    public function test_scorecard_bound_to_a_different_rule_version_blocks_aggregation(): void
    {
        $context = $this->lockedContext();
        $mismatchedRule = $context['rule']->replicate();
        $mismatchedRule->forceFill([
            'version' => 999,
            'lifecycle_state' => 'draft',
            'is_governing' => false,
            'activated_at' => null,
            'activated_by' => null,
            'frozen_at' => null,
        ])->save();
        $context['contest']->scorecards()->firstOrFail()->update([
            'competition_rule_version_id' => $mismatchedRule->getKey(),
        ]);

        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']->fresh());

        $this->assertFalse($result['readiness']['ready']);
        $this->assertContains('scorecard_rule_mismatch', $result['readiness']['blocker_codes']);
    }

    public function test_required_operational_adjustment_evidence_blocks_aggregation_when_missing(): void
    {
        $context = $this->lockedContext();
        \Illuminate\Support\Facades\DB::table('competition_rule_versions')
            ->where('id', $context['rule']->getKey())
            ->update(['deduction_configuration' => json_encode([
                'code' => 'performance_time',
                'type' => 'outside_range_interval',
                'calculation_status' => 'authorized',
                'rounding_policy' => 'ceiling',
            ])]);

        $result = (new JudgeScoreAggregationService)->aggregate($context['contest']->fresh());

        $this->assertFalse($result['readiness']['ready']);
        $this->assertContains('adjustment_evidence_missing', $result['readiness']['blocker_codes']);
    }

    /** @return array{admin: User, event: Event, contest: Contest, rule: \App\Models\CompetitionRuleVersion} */
    private function lockedContext(bool $confirmAggregation = true, ?int $comparisonScale = null): array
    {
        $context = $this->preparedContext();
        if ($comparisonScale !== null) {
            $context['rule']->update(['comparison_scale' => $comparisonScale]);
        }
        if ($confirmAggregation) {
            $context['rule']->confirmAggregation(
                $context['admin'],
                'average',
                'Authorized event committee decision',
                'The committee approved average aggregation.',
            );
        }

        if ($confirmAggregation) {
            (new LockJudgingPanel)->handle($context['admin'], $context['contest']);
        } else {
            $context['contest']->update([
                'judging_panel_locked_at' => now(),
                'judging_panel_locked_by' => $context['admin']->getKey(),
            ]);
        }

        $context['contest'] = $context['contest']->fresh(['entries.entry', 'scorecards', 'ruleVersion']);
        $context['rule'] = $context['contest']->ruleVersion;

        return $context;
    }

    /** @return array{admin: User, event: Event, contest: Contest, rule: \App\Models\CompetitionRuleVersion} */
    private function preparedContext(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = User::query()->where('is_global_admin', true)->first()
            ?? (new BootstrapGlobalAdmin)->handle([
                'name' => 'Global Admin',
                'email' => 'admin@example.com',
                'password' => 'secure-password',
            ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'pop-solo')
            ->firstOrFail()
            ->divisions()
            ->firstOrFail();
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $judges = User::factory()->count(2)->create()->each(
            fn (User $judge) => (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge),
        )->all();
        (new ConfigureJudgingPanel)->handle($admin, $contest, $judges);

        return [
            'admin' => $admin,
            'event' => $event->fresh(),
            'contest' => $contest->fresh(['entries.entry', 'scorecards', 'ruleVersion']),
            'rule' => $contest->ruleVersion,
        ];
    }

    /** @param array<int, array{0: string, 1: string}> $totals */
    private function submitTotals(Contest $contest, array $totals): void
    {
        foreach ($totals as $entryId => $judgeTotals) {
            foreach ($contest->scorecards()->where('entry_id', $entryId)->orderBy('judge_id')->get() as $index => $scorecard) {
                $scorecard->update([
                    'state' => 'submitted',
                    'revision' => 2,
                    'calculated_total' => $judgeTotals[$index],
                    'submitted_at' => now(),
                ]);
            }
        }
    }
}
