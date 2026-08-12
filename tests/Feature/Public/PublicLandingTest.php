<?php

namespace Tests\Feature\Public;

use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\ContestState;
use App\Enums\DivisionPlacementState;
use App\Enums\EventState;
use App\Enums\LedgerEntryType;
use App\Enums\ParticipantMode;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\DivisionPlacementItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\ScoreLedgerEntry;
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
        $first = $this->liveContest($event, 'Basketball', 'Men', 'Court A', ['home' => 44, 'away' => 40, 'period' => 'Q4', 'private' => 'hidden']);
        $second = $this->liveContest($event, 'Volleyball', 'Women', 'Court B', ['home' => 2, 'away' => 1, 'set' => 3, 'phase' => 'Set 3']);
        $this->liveContest($older, 'Earlier Competition', 'Earlier Division', 'Excluded contest', ['home' => 99, 'away' => 0]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('featured_event.name', 'Current SIKLAB')
            ->where('featured_event.slug', 'current-siklab')
            ->where('featured_contest.id', (string) $second->getKey())
            ->where('featured_contest.competition_id', fn ($value) => is_string($value))
            ->where('featured_contest.sides.0.label', 'HOME-'.$second->getKey())
            ->where('featured_contest.live.set', 3)
            ->missing('featured_contest.sides.0.entry')
            ->has('live_contests', 1)
            ->where('live_contests.0.id', (string) $first->getKey())
            ->where('live_contests.0.live.home', 44)
            ->where('live_contests.0.live.period', 'Q4')
            ->missing('live_contests.0.live.private')
            ->where('snapshot_at', fn ($value) => is_string($value))
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
        $bracket = $tournament->bracketVersions()->create([
            'version' => 1,
            'state' => BracketVersionState::Published,
            'generation_algorithm_version' => 'test-v1',
            'draw_order' => [],
            'generation_inputs' => [],
            'created_by' => $creator->getKey(),
            'published_at' => now(),
        ]);
        $delegation = EventDelegation::factory()->create(['event_id' => $event->getKey()]);
        $entry = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'Private Entry Name',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $contest = Contest::factory()->create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $version->getKey(),
            'name' => 'Quarterfinal',
            'state' => 'scheduled',
        ]);
        $node = $bracket->nodes()->create([
            'node_key' => 'R1-N1',
            'node_type' => 'contest',
            'round_number' => 1,
            'sequence' => 1,
            'state' => 'pending',
            'contest_id' => $contest->getKey(),
        ]);
        $node->slots()->create([
            'slot_number' => 1,
            'entry_id' => $entry->getKey(),
            'label' => 'Private slot label',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('competitions.0.divisions.0.has_published_bracket', true)
            ->where('competitions.0.divisions.0.bracket_preview.format', 'single_elimination')
            ->where('competitions.0.divisions.0.bracket_preview.round_label', 'Round 1')
            ->where('competitions.0.divisions.0.bracket_preview.matchups.0.slots.0.label', $delegation->abbreviation)
            ->missing('competitions.0.divisions.0.entries'));
        $this->assertStringNotContainsString('Private Entry Name', json_encode($response->viewData('page')));
        $this->assertStringNotContainsString('Private slot label', json_encode($response->viewData('page')));
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
                ->has('competitions', 0)
                ->has('leaderboard', 0)
                ->where('snapshot_at', fn ($value) => is_string($value)));
    }

    public function test_landing_exposes_signed_leaderboard_totals_without_ranks_or_private_entry_data(): void
    {
        $event = Event::factory()->create([
            'slug' => 'standings-landing',
            'state' => EventState::Live,
            'starts_at' => now(),
        ]);
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);
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
        $placement = DivisionPlacement::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $version->getKey(),
            'state' => DivisionPlacementState::Approved->value,
            'approved_by' => $creator->getKey(),
            'approved_at' => now(),
        ]);

        $totals = [
            ['name' => 'Alpha Delegation', 'abbreviation' => 'ALP', 'color' => 'Red', 'amount' => '35.0000'],
            ['name' => 'Bravo Delegation', 'abbreviation' => 'BRV', 'color' => 'Yellow', 'amount' => '20.0000'],
            ['name' => 'Charlie Delegation', 'abbreviation' => 'CHR', 'color' => 'Purple', 'amount' => '20.0000'],
            ['name' => 'Delta Delegation', 'abbreviation' => 'DLT', 'color' => 'Gray', 'amount' => '10.0000'],
        ];

        foreach ($totals as $index => $total) {
            $delegation = EventDelegation::factory()->create([
                'event_id' => $event->getKey(),
                'name' => $total['name'],
                'abbreviation' => $total['abbreviation'],
                'color' => $total['color'],
            ]);
            $entry = Entry::create([
                'competition_division_id' => $division->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'name' => 'Private Entry '.$total['name'],
                'entry_mode' => ParticipantMode::Team,
                'status' => 'active',
            ]);
            $item = DivisionPlacementItem::create([
                'division_placement_id' => $placement->getKey(),
                'entry_id' => $entry->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'rank' => $index + 1,
                'placement_key' => 'rank-'.($index + 1),
                'championship_points' => $total['amount'],
                'participation_eligible' => true,
            ]);

            ScoreLedgerEntry::create([
                'event_id' => $event->getKey(),
                'event_delegation_id' => $delegation->getKey(),
                'division_placement_id' => $placement->getKey(),
                'division_placement_item_id' => $item->getKey(),
                'entry_type' => LedgerEntryType::Award->value,
                'amount' => $total['amount'],
                'source_key' => 'landing-award-'.$index,
                'source_revision' => 1,
                'created_by' => $creator->getKey(),
                'committed_at' => now(),
            ]);

            if ($index === 0) {
                ScoreLedgerEntry::create([
                    'event_id' => $event->getKey(),
                    'event_delegation_id' => $delegation->getKey(),
                    'division_placement_id' => $placement->getKey(),
                    'division_placement_item_id' => $item->getKey(),
                    'entry_type' => LedgerEntryType::Reversal->value,
                    'amount' => '-5.0000',
                    'source_key' => 'landing-reversal-'.$index,
                    'source_revision' => 2,
                    'created_by' => $creator->getKey(),
                    'committed_at' => now(),
                ]);
            }
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('leaderboard', 4)
            ->where('leaderboard.0.name', 'Alpha Delegation')
            ->where('leaderboard.0.color', 'Red')
            ->where('leaderboard.0.total', fn ($value) => is_string($value) && (float) $value === 30.0)
            ->where('leaderboard.1.name', 'Bravo Delegation')
            ->where('leaderboard.1.total', fn ($value) => is_string($value) && (float) $value === 20.0)
            ->where('leaderboard.2.name', 'Charlie Delegation')
            ->where('leaderboard.2.total', fn ($value) => is_string($value) && (float) $value === 20.0)
            ->where('leaderboard.3.name', 'Delta Delegation')
            ->where('leaderboard.3.total', fn ($value) => is_string($value) && (float) $value === 10.0)
            ->missing('leaderboard.0.rank')
            ->missing('leaderboard.1.rank')
            ->missing('leaderboard.2.rank')
            ->missing('leaderboard.3.rank'));

        $page = json_encode($response->viewData('page'));
        $this->assertIsString($page);
        $this->assertStringNotContainsString('Private Entry Alpha Delegation', $page);
        $this->assertStringNotContainsString('Private Entry Bravo Delegation', $page);
        $this->assertStringNotContainsString('Private Entry Charlie Delegation', $page);
        $this->assertStringNotContainsString('Private Entry Delta Delegation', $page);
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
