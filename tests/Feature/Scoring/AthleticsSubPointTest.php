<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapEventCreator;
use App\Actions\Scoring\ApproveDisciplinePlacements;
use App\Enums\CompetitionFormat;
use App\Enums\DisciplineFamily;
use App\Enums\DisciplineResultState;
use App\Enums\EventRole;
use App\Enums\ParticipantMode;
use App\Enums\ScoringFamily;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Discipline;
use App\Models\DisciplineResult;
use App\Models\Division;
use App\Models\Entry;
use App\Models\EventDelegation;
use App\Models\PlacementPointRule;
use App\Models\PlacementPointTemplate;
use App\Models\User;
use App\Services\DivisionSubPointCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleticsSubPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_discipline_placements_create_sub_points_but_not_championship_points(): void
    {
        $context = $this->context();

        foreach ($context['entries'] as $entry) {
            DisciplineResult::create([
                'discipline_id' => $context['discipline']->getKey(),
                'entry_id' => $entry->getKey(),
                'performance_value' => 10,
                'unit' => 'seconds',
                'state' => DisciplineResultState::Approved,
                'recorded_by' => $context['admin']->getKey(),
                'approved_by' => $context['admin']->getKey(),
                'approved_at' => now(),
            ]);
        }

        (new ApproveDisciplinePlacements)->handle($context['admin'], $context['discipline'], [
            ['entry_id' => $context['entries'][0]->getKey(), 'rank' => 1],
            ['entry_id' => $context['entries'][1]->getKey(), 'rank' => 2],
        ]);

        $standings = (new DivisionSubPointCalculator)->standings($context['division']);

        $this->assertCount(1, $standings);
        $this->assertSame('9', (string) $standings->first()->sub_point_total);
        $this->assertDatabaseCount('score_ledger_entries', 0);
    }

    /** @return array{admin: User, division: Division, discipline: Discipline, entries: list<Entry>} */
    private function context(): array
    {
        $creator = (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB '.uniqid()]);
        $admin = User::factory()->create(['email' => 'admin-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($creator, $event, $admin, EventRole::Admin);
        $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
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
            'points' => '25',
        ]);
        $template->update(['is_signed_off' => true]);
        CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'placement_point_template_id' => $template->getKey(),
            'version' => 1,
            'scoring_family' => ScoringFamily::Aggregate,
            'format' => CompetitionFormat::Aggregate,
            'participant_mode' => ParticipantMode::Team,
            'created_by' => $admin->getKey(),
        ]);
        $discipline = Discipline::create([
            'competition_division_id' => $division->getKey(),
            'code' => '100m-women',
            'name' => "Women's 100m",
            'family' => DisciplineFamily::Track,
            'performance_type' => 'time',
            'canonical_unit' => 'seconds',
            'sort_direction' => 'ascending',
            'sub_point_configuration' => [
                '1' => 5,
                '2' => 4,
                '3' => 3,
                'participation' => 1,
            ],
        ]);
        $entries = [];
        foreach ([1, 2] as $number) {
            $entries[] = Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => 'Relay '.$number,
                'entry_mode' => ParticipantMode::Team,
                'status' => 'active',
            ]);
        }

        return compact('admin', 'division', 'discipline', 'entries');
    }
}
