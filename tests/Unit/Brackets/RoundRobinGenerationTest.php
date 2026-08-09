<?php

namespace Tests\Unit\Brackets;

use App\Actions\Brackets\GenerateDoubleEliminationBracket;
use App\Actions\Brackets\GenerateRoundRobinSchedule;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapEventCreator;
use App\Enums\CompetitionFormat;
use App\Enums\EventRole;
use App\Enums\ParticipantMode;
use App\Enums\RuleVersionState;
use App\Enums\ScoringFamily;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Division;
use App\Models\Entry;
use App\Models\EventDelegation;
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

    public function test_double_elimination_is_blocked_without_a_signed_routing_map(): void
    {
        $context = $this->context(4, CompetitionFormat::DoubleElimination);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('signed-off routing map');
        (new GenerateDoubleEliminationBracket)->handle(
            $context['admin'],
            $context['division'],
            $context['entries']->pluck('id')->all(),
        );
    }

    /** @return array{admin: User, division: Division, entries: Collection<int, Entry>} */
    private function context(int $entryCount, CompetitionFormat $format): array
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
        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => RuleVersionState::ActivatedEditable,
            'is_governing' => true,
            'scoring_family' => ScoringFamily::Objective,
            'format' => $format,
            'participant_mode' => ParticipantMode::Team,
            'created_by' => $admin->getKey(),
        ]);
        $entries = collect();
        for ($index = 1; $index <= $entryCount; $index++) {
            $entries->push(Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => 'Entry '.$index,
                'entry_mode' => ParticipantMode::Team,
                'status' => 'active',
            ]));
        }

        return compact('admin', 'division', 'entries', 'version');
    }
}
