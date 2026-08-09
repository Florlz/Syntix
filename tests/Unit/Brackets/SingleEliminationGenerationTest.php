<?php

namespace Tests\Unit\Brackets;

use App\Actions\Brackets\GenerateSingleEliminationBracket;
use App\Actions\Brackets\PublishBracket;
use App\Actions\Events\CreateEvent;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Enums\CompetitionFormat;
use App\Enums\ParticipantMode;
use App\Enums\ScoringFamily;
use App\Enums\TournamentState;
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

class SingleEliminationGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_entries_create_three_byes_and_a_third_place_playoff(): void
    {
        $context = $this->context(5);
        $tournament = (new GenerateSingleEliminationBracket)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );
        $bracket = $tournament->bracketVersions()->firstOrFail();

        $this->assertSame(TournamentState::Preview, $tournament->tournamentState());
        $this->assertSame(3, $bracket->nodes()->where('node_type', 'bye')->count());
        $this->assertSame(4, $bracket->nodes()->where('node_type', 'contest')->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'third_place')->count());
        $this->assertDatabaseHas('bracket_versions', [
            'id' => $bracket->getKey(),
            'state' => 'preview',
            'generation_algorithm_version' => 'single-elimination-baseline-v1',
        ]);

        (new PublishBracket)->handle($context['admin'], $bracket);

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->getKey(),
            'state' => 'published',
        ]);
    }

    public function test_one_entry_is_uncontested_without_a_bracket_or_automatic_champion(): void
    {
        $context = $this->context(1);
        $tournament = (new GenerateSingleEliminationBracket)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );

        $this->assertSame(TournamentState::Uncontested, $tournament->tournamentState());
        $this->assertDatabaseCount('bracket_versions', 0);
        $this->assertDatabaseCount('score_ledger_entries', 0);
    }

    /** @return array{admin: User, division: Division, entries: Collection<int, Entry>} */
    private function context(int $entryCount): array
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
            'format' => CompetitionFormat::SingleElimination,
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
                'status' => 'active',
            ]));
        }

        return compact('admin', 'division', 'entries');
    }
}
