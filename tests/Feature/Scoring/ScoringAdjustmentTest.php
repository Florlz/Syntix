<?php

namespace Tests\Feature\Scoring;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Actions\Scoring\RecordScoringAdjustment;
use App\Actions\Scoring\VoidScoringAdjustment;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Competition;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolved_interval_rounding_blocks_automatic_story_adjustment(): void
    {
        $context = $this->context('story-telling');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('rounding');

        (new RecordScoringAdjustment)->handle(
            $context['tabulator'],
            $context['contest'],
            $context['entry'],
            'performance_time',
            '270',
            'seconds',
        );
    }

    public function test_authorized_rounding_calculates_story_and_radio_points_on_the_server(): void
    {
        $story = $this->context('story-telling');
        $this->authorizeRounding($story, 'ceiling');
        $adjustment = (new RecordScoringAdjustment)->handle(
            $story['tabulator'], $story['contest'], $story['entry'],
            'performance_time', '269', 'seconds',
        );
        $this->assertSame('2.0000', (string) $adjustment->points);

        $radio = $this->context('radio-drama');
        $this->authorizeRounding($radio, 'ceiling');
        $adjustment = (new RecordScoringAdjustment)->handle(
            $radio['tabulator'], $radio['contest'], $radio['entry'],
            'performance_time', '451', 'seconds',
        );
        $this->assertSame('10.0000', (string) $adjustment->points);
    }

    public function test_recording_adjustment_preserves_judge_raw_total_and_rejects_duplicate_active_code(): void
    {
        $context = $this->context('story-telling');
        $this->authorizeRounding($context, 'ceiling');
        $scorecard = EntryScorecard::create([
            'contest_id' => $context['contest']->getKey(),
            'entry_id' => $context['entry']->getKey(),
            'judge_id' => User::factory()->create()->getKey(),
            'competition_rule_version_id' => $context['rule']->getKey(),
            'state' => 'submitted',
            'revision' => 2,
            'calculated_total' => '88.5000',
        ]);

        (new RecordScoringAdjustment)->handle(
            $context['tabulator'], $context['contest'], $context['entry'],
            'performance_time', '269', 'seconds',
        );
        $this->assertSame('88.5000', (string) $scorecard->fresh()->calculated_total);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('void');
        (new RecordScoringAdjustment)->handle(
            $context['tabulator'], $context['contest'], $context['entry'],
            'performance_time', '260', 'seconds',
        );
    }

    public function test_void_keeps_adjustment_history_and_requires_reason(): void
    {
        $context = $this->context('story-telling');
        $this->authorizeRounding($context, 'ceiling');
        $adjustment = (new RecordScoringAdjustment)->handle(
            $context['tabulator'], $context['contest'], $context['entry'],
            'performance_time', '270', 'seconds',
        );

        $voided = (new VoidScoringAdjustment)->handle($context['tabulator'], $adjustment, 'Timer was reset late');

        $this->assertNotNull($voided->voided_at);
        $this->assertSame($context['tabulator']->getKey(), $voided->voided_by);
        $this->assertSame(1, $context['contest']->adjustments()->withVoided()->count());
        $this->assertSame(0, $context['contest']->adjustments()->count());
    }

    public function test_unassigned_tabulator_and_mismatched_entry_are_rejected(): void
    {
        $context = $this->context('story-telling');
        $this->authorizeRounding($context, 'ceiling');
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        (new RecordScoringAdjustment)->handle(
            $outsider, $context['contest'], $context['entry'],
            'performance_time', '270', 'seconds',
        );
    }

    public function test_completed_contest_rejects_new_adjustments(): void
    {
        $context = $this->context('story-telling');
        $this->authorizeRounding($context, 'ceiling');
        $context['contest']->update(['state' => 'completed']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Reopen');

        (new RecordScoringAdjustment)->handle(
            $context['tabulator'], $context['contest']->fresh(), $context['entry'],
            'performance_time', '270', 'seconds',
        );
    }

    public function test_completed_contest_rejects_voiding_an_adjustment(): void
    {
        $context = $this->context('story-telling');
        $this->authorizeRounding($context, 'ceiling');
        $adjustment = (new RecordScoringAdjustment)->handle(
            $context['tabulator'], $context['contest'], $context['entry'],
            'performance_time', '270', 'seconds',
        );
        $context['contest']->update(['state' => 'completed']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Reopen');

        (new VoidScoringAdjustment)->handle($context['tabulator'], $adjustment, 'Late correction');
    }

    /** @return array<string, mixed> */
    private function context(string $slug): array
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
        $division = Competition::query()->whereBelongsTo($event)->where('slug', $slug)->firstOrFail()->divisions()->firstOrFail();
        $contest = (new PrepareJudgedContest)->handle($admin, $division);
        $tabulator = User::factory()->create();
        (new GrantEventRole)->handle($admin, $event, $tabulator, EventRole::Tabulator);
        (new GrantScoringAssignment)->handle(
            $admin, $event, $tabulator, ScoringAssignmentScope::Contest, $contest,
        );

        return [
            'admin' => $admin,
            'event' => $event,
            'tabulator' => $tabulator,
            'contest' => $contest,
            'entry' => $contest->entries()->firstOrFail()->entry,
            'rule' => $contest->ruleVersion,
        ];
    }

    /** @param array<string, mixed> $context */
    private function authorizeRounding(array $context, string $rounding): void
    {
        $configuration = $context['rule']->deduction_configuration;
        $configuration['rounding_policy'] = $rounding;
        $configuration['calculation_status'] = 'authorized';
        $context['rule']->update(['deduction_configuration' => $configuration]);
    }
}
