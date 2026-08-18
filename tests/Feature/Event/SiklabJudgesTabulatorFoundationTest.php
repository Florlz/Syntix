<?php

namespace Tests\Feature\Event;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\BackfillSiklabScoringMetadata;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Data\CompetitionRuleMetadata;
use App\Enums\RuleVersionState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Event;
use App\Models\User;
use App\Support\Siklab2025Programme;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiklabJudgesTabulatorFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_judged_definitions_keep_exact_rubrics_and_explicit_source_blockers(): void
    {
        $definitions = collect(Siklab2025Programme::judgedCompetitions())->keyBy('name');

        $this->assertSame([
            ['Content and clear organization', 35],
            ['Delivery', 35],
            ['Pronunciation, enunciation and diction', 20],
            ['Stage presence', 10],
        ], $definitions['Story Telling']['criteria']);
        $this->assertSame('confirmed', $definitions['Story Telling']['reliability_label']);
        $this->assertSame([20], $definitions['Story Telling']['source_pages']);
        $this->assertSame('Nov 12', $definitions['Story Telling']['programme_day_hint']);
        $this->assertSame([20], $definitions['Essay Writing']['source_pages']);
        $this->assertSame([20, 21], $definitions['Radio Drama']['source_pages']);
        $this->assertSame([22, 23], $definitions['Instrumental Solo — Piano']['source_pages']);
        $this->assertSame([24, 25], $definitions['Contemporary Dance']['source_pages']);
        $this->assertSame([25], $definitions['Dance Sports']['source_pages']);
        $this->assertSame([25], $definitions['Cheer Dance']['source_pages']);

        $this->assertSame([
            ['Tone Quality', 40],
            ['Musicianship', 40],
            ['Deportment', 20],
        ], $definitions['Pop Solo']['criteria']);
        $this->assertSame([
            ['Performance', 40],
            ['Interpretation', 30],
            ['Costume, music and equipment', 20],
            ['Overall Impact', 10],
        ], $definitions['Folk Dance']['criteria']);
        $this->assertSame([
            ['Concept', 35],
            ['Techniques', 35],
            ['Composition', 30],
        ], $definitions['Photography']['criteria']);

        $this->assertSame('blocked', $definitions['Essay Writing']['source_status']);
        $this->assertSame('conflict', $definitions['Essay Writing']['reliability_label']);
        $this->assertSame(
            'Criteria total 95 while the source prints 100.',
            $definitions['Essay Writing']['source_blocker'],
        );
        $this->assertSame(95, collect($definitions['Essay Writing']['criteria'])->sum(1));

        $this->assertSame('blocked', $definitions['Dance Sports']['source_status']);
        $this->assertSame('unresolved', $definitions['Dance Sports']['reliability_label']);
        $this->assertSame(
            'The proposal lists criteria without weights.',
            $definitions['Dance Sports']['source_blocker'],
        );
        $this->assertSame('blocked', $definitions['Cheer Dance']['source_status']);
        $this->assertSame('conflict', $definitions['Cheer Dance']['reliability_label']);
        $this->assertSame(
            'Overall Impact is printed as 100 percent, producing an invalid total.',
            $definitions['Cheer Dance']['source_blocker'],
        );
    }

    public function test_application_persists_metadata_through_an_explicit_read_dto_and_hides_raw_configuration(): void
    {
        [$admin, $event] = $this->programme();

        $storyRule = $this->ruleFor($event, 'story-telling');
        $metadata = $storyRule->metadata();

        $this->assertInstanceOf(CompetitionRuleMetadata::class, $metadata);
        $this->assertSame('confirmed', $metadata->reliabilityLabel);
        $this->assertSame([20], $metadata->sourcePages);
        $this->assertSame(['CSPC Auditorium', 'Library Commons'], $metadata->venueCandidates);
        $this->assertSame('Nov 12', $metadata->programmeDayHint);
        $this->assertSame('outside_range_interval', $metadata->deductionConfiguration['type']);
        $this->assertSame(300, $metadata->deductionConfiguration['minimum_seconds']);
        $this->assertSame(420, $metadata->deductionConfiguration['maximum_seconds']);
        $this->assertSame(30, $metadata->deductionConfiguration['interval_seconds']);
        $this->assertSame(1, $metadata->deductionConfiguration['points_per_interval']);
        $this->assertSame('blocked', $metadata->deductionConfiguration['calculation_status']);

        $this->assertArrayNotHasKey('scoring_configuration', $storyRule->toArray());
        $this->assertArrayNotHasKey('scoring_configuration', $storyRule->jsonSerialize());
    }

    public function test_application_upserts_the_proposal_venues_without_fabricating_schedules(): void
    {
        [$admin, $event] = $this->programme();
        (new ApplySiklab2025Programme)->handle($admin, $event);

        $expectedVenues = [
            'CSPC Gymnasium',
            'CSPC Sepak Takraw Court',
            'CSPC Duran Hall',
            'Academic Building III — Ground Floor',
            'CSPC Library',
            'Nabua Central Pilot School Oval',
            'Nabua Central Pilot School',
            'CSPC Auditorium',
            'Library Commons',
            'CEA Drafting Room',
            'Pearl Function Hall',
            'CSPC Grounds',
        ];

        $this->assertSame($expectedVenues, $event->venues()->orderBy('id')->pluck('name')->all());
        $this->assertSame(0, $event->schedules()->count());
    }

    public function test_seeded_average_remains_an_unconfirmed_aggregation_candidate(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');

        $this->assertSame('average', $rule->judge_aggregation_method);
        $this->assertNull($rule->aggregationConfirmation());
        $this->assertFalse($rule->hasConfirmedAggregation());
        $this->assertFalse($rule->isAggregationReady());
    }

    public function test_global_admin_can_confirm_aggregation_on_a_mutable_rule_with_provenance(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');

        $rule->confirmAggregation(
            $admin,
            'average',
            'Authorized event committee decision 2025-08-18',
            'The committee approved the candidate method for this event.',
        );

        $confirmation = $rule->fresh()->aggregationConfirmation();

        $this->assertSame('average', $confirmation['method']);
        $this->assertSame($admin->getKey(), $confirmation['confirmed_by']);
        $this->assertSame('Authorized event committee decision 2025-08-18', $confirmation['reference']);
        $this->assertSame('The committee approved the candidate method for this event.', $confirmation['reason']);
        $this->assertNotEmpty($confirmation['confirmed_at']);
        $this->assertTrue($rule->fresh()->hasConfirmedAggregation());
        $this->assertTrue($rule->fresh()->isAggregationReady());
    }

    public function test_frozen_rule_rejects_aggregation_confirmation(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');
        $rule->update(['lifecycle_state' => RuleVersionState::Frozen]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('mutable');

        $rule->confirmAggregation($admin, 'average', 'Reference', 'Reason');
    }

    public function test_scoring_started_rule_rejects_aggregation_confirmation(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');
        $rule->division()->update(['scoring_started_at' => now()]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('mutable');

        $rule->confirmAggregation($admin, 'average', 'Reference', 'Reason');
    }

    public function test_metadata_backfill_is_safe_idempotent_and_preserves_calculation_configuration(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');
        $originalDeduction = $rule->deduction_configuration;
        $configuration = $rule->scoring_configuration;
        unset($configuration['venue_candidates'], $configuration['source_pages']);
        $configuration['operator_note'] = 'keep this';
        $rule->update(['scoring_configuration' => $configuration]);

        $report = (new BackfillSiklabScoringMetadata)->handle($event->fresh(), false, $admin);
        $updated = $rule->fresh();

        $this->assertGreaterThan(0, $report['updated']);
        $this->assertSame(['CSPC Auditorium'], $updated->metadata()->venueCandidates);
        $this->assertSame([21, 22], $updated->metadata()->sourcePages);
        $this->assertSame('keep this', $updated->scoring_configuration['operator_note']);
        $this->assertSame($originalDeduction, $updated->deduction_configuration);

        $second = (new BackfillSiklabScoringMetadata)->handle($updated->eventRecord(), false, $admin);
        $this->assertSame(0, $second['updated']);
    }

    public function test_frozen_rule_backfill_does_not_change_calculation_fields(): void
    {
        [$admin, $event] = $this->programme();
        $rule = $this->ruleFor($event, 'pop-solo');
        $configuration = $rule->scoring_configuration;
        unset($configuration['venue_candidates']);
        $rule->update(['scoring_configuration' => $configuration]);
        $rule->update(['lifecycle_state' => RuleVersionState::Frozen]);
        $before = [
            'judge_aggregation_method' => $rule->judge_aggregation_method,
            'deduction_configuration' => $rule->deduction_configuration,
        ];

        (new BackfillSiklabScoringMetadata)->handle($event->fresh(), false, $admin);

        $after = $rule->fresh();
        $this->assertSame($before['judge_aggregation_method'], $after->judge_aggregation_method);
        $this->assertSame($before['deduction_configuration'], $after->deduction_configuration);
        $this->assertDatabaseHas('audit_logs', ['action' => 'scoring_metadata.backfilled']);
    }

    public function test_unresolved_interval_rounding_blocks_automatic_calculation(): void
    {
        [$admin, $event] = $this->programme();

        foreach (['story-telling', 'radio-drama'] as $slug) {
            $deduction = $this->ruleFor($event, $slug)->metadata()->deductionConfiguration;

            $this->assertSame('outside_range_interval', $deduction['type']);
            $this->assertSame(30, $deduction['interval_seconds']);
            $this->assertSame('blocked', $deduction['calculation_status']);
            $this->assertNull($deduction['rounding_policy']);
        }
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

        return [$admin, $event->fresh()];
    }

    private function ruleFor(Event $event, string $slug): CompetitionRuleVersion
    {
        return Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', $slug)
            ->firstOrFail()
            ->divisions()
            ->firstOrFail()
            ->ruleVersions()
            ->firstOrFail();
    }
}
