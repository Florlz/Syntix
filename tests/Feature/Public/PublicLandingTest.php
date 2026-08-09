<?php

namespace Tests\Feature\Public;

use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\ContestState;
use App\Enums\EventState;
use App\Enums\ParticipantMode;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
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

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_features_the_latest_live_event_and_all_its_live_contests(): void
    {
        $older = Event::factory()->create([
            'name' => 'Earlier SIKLAB',
            'slug' => 'earlier-siklab',
            'state' => EventState::Live,
            'starts_at' => now()->subDay(),
        ]);
        $event = Event::factory()->create([
            'name' => 'Current SIKLAB',
            'slug' => 'current-siklab',
            'state' => EventState::Live,
            'starts_at' => now(),
        ]);
        $first = $this->liveContest($event, 'Basketball', 'Men', 'Court A', ['home' => 44, 'away' => 40, 'private' => 'hidden']);
        $second = $this->liveContest($event, 'Volleyball', 'Women', 'Court B', ['home' => 2, 'away' => 1, 'phase' => 'Set 3']);
        $this->liveContest($older, 'Earlier Competition', 'Earlier Division', 'Excluded contest', ['home' => 99, 'away' => 0]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('featured_event.name', 'Current SIKLAB')
            ->where('featured_event.slug', 'current-siklab')
            ->where('featured_contest.id', (string) $second->getKey())
            ->where('featured_contest.sides.0.label', 'HOME-'.$second->getKey())
            ->missing('featured_contest.sides.0.entry')
            ->has('live_contests', 1)
            ->where('live_contests.0.id', (string) $first->getKey())
            ->where('live_contests.0.live.home', 44)
            ->missing('live_contests.0.live.private')
            ->where('auth.user', null)
            ->where('flash.setup_url', null));
        $this->assertStringNotContainsString('Private Home Entry', json_encode($response->viewData('page')));
        $this->assertStringNotContainsString('Private Away Entry', json_encode($response->viewData('page')));
    }

    public function test_landing_exposes_published_bracket_availability_without_entry_names(): void
    {
        $event = Event::factory()->create([
            'slug' => 'bracket-landing',
            'state' => EventState::Live,
            'starts_at' => now(),
        ]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Basketball']);
        $division = Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => 'Men']);
        $creator = User::factory()->create();
        $version = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => 'frozen',
            'is_governing' => true,
            'format' => CompetitionFormat::SingleElimination,
            'participant_mode' => ParticipantMode::Team,
            'created_by' => $creator->getKey(),
        ]);
        $tournament = Tournament::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $version->getKey(),
            'format' => CompetitionFormat::SingleElimination,
            'state' => TournamentState::Published,
            'eligible_entry_count' => 2,
            'created_by' => $creator->getKey(),
            'draw_locked_at' => now(),
            'published_at' => now(),
        ]);
        $tournament->bracketVersions()->create([
            'version' => 1,
            'state' => BracketVersionState::Published,
            'generation_algorithm_version' => 'test-v1',
            'draw_order' => [],
            'generation_inputs' => [],
            'created_by' => $creator->getKey(),
            'published_at' => now(),
        ]);
        $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
        Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'Private Entry Name',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('competitions.0.divisions.0.has_published_bracket', true)
            ->missing('competitions.0.divisions.0.entries'));
        $this->assertStringNotContainsString('Private Entry Name', json_encode($response->viewData('page')));
    }

    public function test_landing_has_a_truthful_no_live_event_state(): void
    {
        Event::factory()->create(['state' => EventState::Preparation]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('featured_event', null)
                ->where('featured_contest', null)
                ->has('live_contests', 0)
                ->has('competitions', 0));
    }

    public function test_authenticated_staff_gets_dashboard_navigation_without_private_flash_or_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession([
                'setup_url' => 'https://private.example/setup',
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['private' => 'Sensitive validation error'])),
            ])
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', (string) $user->getKey())
                ->where('flash.setup_url', null)
                ->missing('errors.private'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function liveContest(Event $event, string $competitionName, string $divisionName, string $contestName, array $payload): Contest
    {
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => $competitionName]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => $divisionName]);

        $contest = Contest::factory()->create([
            'competition_division_id' => $division->getKey(),
            'name' => $contestName,
            'state' => ContestState::Live,
            'live_payload' => $payload,
            'revision' => 7,
            'updated_at' => now(),
        ]);
        $homeDelegation = EventDelegation::factory()->create([
            'event_id' => $event->getKey(),
            'name' => 'Home Delegation '.$contest->getKey(),
            'abbreviation' => 'HOME-'.$contest->getKey(),
        ]);
        $awayDelegation = EventDelegation::factory()->create([
            'event_id' => $event->getKey(),
            'name' => 'Away Delegation '.$contest->getKey(),
            'abbreviation' => 'AWAY-'.$contest->getKey(),
        ]);
        $homeEntry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $homeDelegation->getKey(),
            'name' => 'Private Home Entry',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $awayEntry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $awayDelegation->getKey(),
            'name' => 'Private Away Entry',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $contest->entries()->createMany([
            ['entry_id' => $homeEntry->getKey(), 'slot' => 1],
            ['entry_id' => $awayEntry->getKey(), 'slot' => 2],
        ]);

        return $contest;
    }
}
