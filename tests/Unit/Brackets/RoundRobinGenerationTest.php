<?php

namespace Tests\Unit\Brackets;

use App\Actions\Brackets\GenerateDoubleEliminationBracket;
use App\Actions\Brackets\GenerateRoundRobinSchedule;
use App\Actions\Events\CreateEvent;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Enums\CompetitionFormat;
use App\Enums\ParticipantMode;
use App\Enums\ScoringFamily;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Division;
use App\Models\Entry;
use App\Models\EventDelegation;
use App\Models\PlacementPointRule;
use App\Models\PlacementPointTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RoundRobinGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_entries_create_every_pair_without_fake_rest_contests(): void
    {
        $context = $this->context(5, CompetitionFormat::RoundRobin);
        $tournament = (new GenerateRoundRobinSchedule)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );

        $this->assertSame(10, $tournament->bracketVersions()->firstOrFail()->nodes()->count());
        $this->assertDatabaseMissing('bracket_nodes', ['node_type' => 'bye']);
        $this->assertDatabaseCount('contests', 10);
    }

    public function test_four_entries_create_a_routed_double_elimination_bracket_with_a_conditional_reset(): void
    {
        $context = $this->context(4, CompetitionFormat::DoubleElimination);
        $tournament = (new GenerateDoubleEliminationBracket)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );

        $bracket = $tournament->bracketVersions()->firstOrFail();
        $this->assertSame(7, $bracket->nodes()->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'reset_final')->where('state', 'pending')->count());
        $this->assertDatabaseHas('bracket_versions', [
            'id' => $bracket->getKey(),
            'generation_algorithm_version' => 'double-elimination-2-4-8-v1',
        ]);
    }

    public function test_round_robin_generation_rejects_an_active_entry(): void
    {
        $context = $this->context(2, CompetitionFormat::RoundRobin);
        $context['entries']->last()->update(['status' => 'active']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approve every participating team sheet before making the draw.');

        (new GenerateRoundRobinSchedule)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );
    }

    public function test_double_elimination_generation_rejects_an_active_entry(): void
    {
        $context = $this->context(2, CompetitionFormat::DoubleElimination);
        $context['entries']->last()->update(['status' => 'active']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approve every participating team sheet before making the draw.');

        (new GenerateDoubleEliminationBracket)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );
    }

    /** @return array{admin: User, division: Division, entries: Collection<int, Entry>} */
    private function context(int $entryCount, CompetitionFormat $format): array
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);

        $template = PlacementPointTemplate::create([
            'event_id' => $event->getKey(),
            'code' => 'major-'.uniqid(),
            'name' => 'Major',
            'version' => 1,
            'is_signed_off' => false,
        ]);
        PlacementPointRule::create([
            'placement_point_template_id' => $template->getKey(),
            'placement_key' => 'champion',
            'points' => '25.0000',
        ]);
        $template->update(['is_signed_off' => true]);

        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'placement_point_template_id' => $template->getKey(),
            'version' => 1,
            'scoring_family' => ScoringFamily::Objective,
            'format' => $format,
            'participant_mode' => ParticipantMode::Team,
            'input_scale' => 0,
            'calculation_scale' => 0,
            'display_scale' => 0,
            'rounding_mode' => 'none',
            'rounding_stage' => 'final',
            'tie_breaker_configuration' => ['mode' => 'manual_resolution_required'],
            'participation_configuration' => ['policy' => 'institutional'],
            'publication_configuration' => ['live' => true],
            'approval_configuration' => ['admin_required' => true],
            'source_status' => 'verified',
            'created_by' => $admin->getKey(),
        ]);
        (new ActivateRuleVersion)->handle($admin, $version);
        $entries = collect();
        for ($index = 1; $index <= $entryCount; $index++) {
            $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
            $entries->push(Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => 'Entry '.$index,
                'entry_mode' => ParticipantMode::Team,
                'status' => 'locked',
            ]));
        }

        return compact('admin', 'division', 'entries', 'version');
    }
}
