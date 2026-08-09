<?php

namespace Tests\Feature\Public;

use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\ParticipantMode;
use App\Enums\TournamentState;
use App\Models\BracketVersion;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PublicBracketTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_bracket_is_anonymous_and_uses_sanitized_delegation_labels(): void
    {
        [$event, $division, $bracket] = $this->bracket();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession([
                'setup_url' => 'https://private.example/setup',
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['private' => 'Sensitive validation error'])),
            ])
            ->get(route('public.bracket', [$event, $division]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Bracket')
            ->where('auth.user', null)
            ->where('flash.setup_url', null)
            ->missing('errors.private')
            ->where('bracket.version', 1)
            ->where('bracket.nodes.0.slots.0.label', 'CSPC')
            ->missing('bracket.nodes.0.slots.0.entry'));
        $this->assertStringNotContainsString('Private Participant Name', json_encode($response->viewData('page')));
        $this->assertNotSame('private, no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(BracketVersionState::Published, $bracket->versionState());
    }

    public function test_unpublished_bracket_is_not_public(): void
    {
        [$event, $division, $bracket] = $this->bracket();
        $bracket->update(['state' => BracketVersionState::Preview, 'published_at' => null]);
        $bracket->tournament()->update(['state' => TournamentState::Preview, 'published_at' => null]);

        $this->get(route('public.bracket', [$event, $division]))->assertNotFound();
    }

    public function test_division_must_belong_to_the_public_event(): void
    {
        [$event] = $this->bracket();
        $otherEvent = Event::factory()->create(['slug' => 'other-event']);
        $otherCompetition = Competition::factory()->create(['event_id' => $otherEvent->getKey()]);
        $otherDivision = Division::factory()->create(['competition_id' => $otherCompetition->getKey()]);

        $this->get(route('public.bracket', [$event, $otherDivision]))->assertNotFound();
    }

    /** @return array{Event, Division, BracketVersion} */
    private function bracket(): array
    {
        $event = Event::factory()->create(['slug' => 'public-bracket-test']);
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Basketball']);
        $division = Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => 'Men']);
        $delegation = EventDelegation::factory()->create([
            'event_id' => $event->getKey(),
            'name' => 'Camarines Sur Polytechnic Colleges',
            'abbreviation' => 'CSPC',
        ]);
        $entry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'Private Participant Name',
            'entry_mode' => ParticipantMode::Individual,
            'status' => 'active',
        ]);
        $creator = User::factory()->create();
        $ruleVersion = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => 'frozen',
            'is_governing' => true,
            'format' => CompetitionFormat::SingleElimination,
            'participant_mode' => ParticipantMode::Individual,
            'created_by' => $creator->getKey(),
        ]);
        $tournament = Tournament::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $ruleVersion->getKey(),
            'format' => CompetitionFormat::SingleElimination,
            'state' => TournamentState::Published,
            'eligible_entry_count' => 2,
            'created_by' => $creator->getKey(),
            'draw_locked_at' => now(),
            'published_at' => now(),
        ]);
        $bracket = $tournament->bracketVersions()->create([
            'version' => 1,
            'state' => BracketVersionState::Published,
            'generation_algorithm_version' => 'test-v1',
            'draw_order' => [$entry->getKey()],
            'generation_inputs' => ['entry_count' => 1],
            'created_by' => $creator->getKey(),
            'published_at' => now(),
        ]);
        $node = $bracket->nodes()->create([
            'node_key' => 'R1-N1',
            'node_type' => BracketNodeType::Bye,
            'round_number' => 1,
            'sequence' => 1,
            'state' => 'bye_resolved',
        ]);
        $node->slots()->createMany([
            ['slot_number' => 1, 'entry_id' => $entry->getKey()],
            ['slot_number' => 2, 'label' => 'BYE'],
        ]);

        return [$event, $division, $bracket];
    }
}
