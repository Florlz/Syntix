<?php

namespace Tests\Feature\Event;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Assignments\RevokeScoringAssignment;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\User;
use App\Policies\ContestPolicy;
use App\Policies\DivisionPolicy;
use App\Policies\EntryScorecardPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringAssignmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_assignment_contains_current_and_future_contests_only(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        [$division, $siblingDivision] = $this->divisionsFor($event);
        $assignedContest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $futureContest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $siblingContest = Contest::factory()->create(['competition_division_id' => $siblingDivision->getKey()]);

        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::CompetitionDivision,
            $division,
        );

        $policy = new ContestPolicy;
        $this->assertTrue($policy->score($tabulator, $assignedContest));
        $this->assertTrue($policy->score($tabulator, $futureContest));
        $this->assertFalse($policy->score($tabulator, $siblingContest));
        $this->assertFalse((new DivisionPolicy)->view($tabulator, $division));
    }

    public function test_contest_and_scorecard_scopes_do_not_inherit_to_children_or_parents(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        $judge = User::factory()->create(['email' => 'judge@example.com']);
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $siblingContest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $scorecard = EntryScorecard::factory()->create(['contest_id' => $contest->getKey()]);
        $siblingScorecard = EntryScorecard::factory()->create(['contest_id' => $siblingContest->getKey()]);

        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::Contest,
            $contest,
        );
        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judge,
            ScoringAssignmentScope::EntryScorecard,
            $scorecard,
        );

        $this->assertTrue((new ContestPolicy)->score($tabulator, $contest));
        $this->assertFalse((new ContestPolicy)->score($tabulator, $siblingContest));
        $this->assertFalse((new EntryScorecardPolicy)->score($tabulator, $scorecard));
        $this->assertTrue((new EntryScorecardPolicy)->score($judge, $scorecard));
        $this->assertFalse((new EntryScorecardPolicy)->score($judge, $siblingScorecard));
        $this->assertFalse((new DivisionPolicy)->view($tabulator, $division));
    }

    public function test_overlapping_assignments_preserve_access_until_both_are_revoked(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);

        $divisionAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::CompetitionDivision,
            $division,
        );
        $contestAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::Contest,
            $contest,
        );

        $this->assertTrue((new ContestPolicy)->score($tabulator, $contest));

        (new RevokeScoringAssignment)->handle($divisionAssignment, $creator, 'Narrowing the assignment');
        $this->assertTrue((new ContestPolicy)->score($tabulator->fresh(), $contest));

        (new RevokeScoringAssignment)->handle($contestAssignment, $creator, 'Assignment ended');
        $this->assertFalse((new ContestPolicy)->score($tabulator->fresh(), $contest));
    }

    public function test_assignment_target_must_belong_to_the_selected_event(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        $otherEvent = Event::factory()->create(['name' => 'Other Event', 'slug' => 'other-event']);
        $otherCompetition = Competition::factory()->create(['event_id' => $otherEvent->getKey()]);
        $otherDivision = Division::factory()->create(['competition_id' => $otherCompetition->getKey()]);

        $this->expectException(\DomainException::class);
        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::CompetitionDivision,
            $otherDivision,
        );
    }

    /** @return array{0: User, 1: Event, 2: User} */
    private function eventWithTabulator(): array
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        $tabulator = User::factory()->create(['email' => 'tabulator@example.com']);
        (new GrantEventRole)->handle($creator, $event, $tabulator, EventRole::Tabulator);

        return [$creator, $event, $tabulator];
    }

    /** @return array{0: Division, 1: Division} */
    private function divisionsFor(Event $event): array
    {
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $siblingCompetition = Competition::factory()->create(['event_id' => $event->getKey()]);

        return [
            Division::factory()->create(['competition_id' => $competition->getKey()]),
            Division::factory()->create(['competition_id' => $siblingCompetition->getKey()]),
        ];
    }

    private function bootstrapCreator(): User
    {
        return (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'a-secure-bootstrap-password',
        ]);
    }
}
