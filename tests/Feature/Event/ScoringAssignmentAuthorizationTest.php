<?php

namespace Tests\Feature\Event;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Assignments\RevokeScoringAssignment;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Events\RevokeEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\ScoringAssignment;
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
        $scorecard = EntryScorecard::factory()->create([
            'contest_id' => $contest->getKey(),
            'judge_id' => $judge->getKey(),
        ]);
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

    public function test_judge_cannot_receive_a_division_assignment(): void
    {
        [$creator, $event] = $this->eventWithTabulator();
        $judge = User::factory()->create(['email' => 'division-judge@example.com']);
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);
        [$division] = $this->divisionsFor($event);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('event role required by this scope');

        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judge,
            ScoringAssignmentScope::CompetitionDivision,
            $division,
        );
    }

    public function test_judge_cannot_receive_a_contest_assignment(): void
    {
        [$creator, $event] = $this->eventWithTabulator();
        $judge = User::factory()->create(['email' => 'contest-judge@example.com']);
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('event role required by this scope');

        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judge,
            ScoringAssignmentScope::Contest,
            $contest,
        );
    }

    public function test_tabulator_cannot_receive_an_entry_scorecard_assignment(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $scorecard = EntryScorecard::factory()->create(['contest_id' => $contest->getKey()]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('event role required by this scope');

        (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $tabulator,
            ScoringAssignmentScope::EntryScorecard,
            $scorecard,
        );
    }

    public function test_division_assignment_never_grants_judge_scorecard_access(): void
    {
        [$creator, $event] = $this->eventWithTabulator();
        $judge = User::factory()->create(['email' => 'division-fallback-judge@example.com']);
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $scorecard = EntryScorecard::factory()->create([
            'contest_id' => $contest->getKey(),
            'judge_id' => $judge->getKey(),
        ]);

        ScoringAssignment::create([
            'event_id' => $event->getKey(),
            'user_id' => $judge->getKey(),
            'scope_type' => ScoringAssignmentScope::CompetitionDivision,
            'competition_division_id' => $division->getKey(),
            'granted_at' => now(),
        ]);

        $this->assertFalse((new EntryScorecardPolicy)->score($judge, $scorecard));
    }

    public function test_role_revocation_removes_only_incompatible_assignments(): void
    {
        [$creator, $event] = $this->eventWithTabulator();
        $judgeTabulator = User::factory()->create(['email' => 'dual-role@example.com']);
        $judgeMembership = (new GrantEventRole)->handle($creator, $event, $judgeTabulator, EventRole::Judge);
        $tabulatorMembership = (new GrantEventRole)->handle($creator, $event, $judgeTabulator, EventRole::Tabulator);
        [$division] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $scorecard = EntryScorecard::factory()->create(['contest_id' => $contest->getKey()]);

        $divisionAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judgeTabulator,
            ScoringAssignmentScope::CompetitionDivision,
            $division,
        );
        $contestAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judgeTabulator,
            ScoringAssignmentScope::Contest,
            $contest,
        );
        $scorecardAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judgeTabulator,
            ScoringAssignmentScope::EntryScorecard,
            $scorecard,
        );

        (new RevokeEventRole)->handle($judgeMembership, $creator, 'Judge assignment ended');

        $this->assertNotNull($scorecardAssignment->fresh()->revoked_at);
        $this->assertNull($divisionAssignment->fresh()->revoked_at);
        $this->assertNull($contestAssignment->fresh()->revoked_at);

        (new RevokeEventRole)->handle($tabulatorMembership, $creator, 'Tabulator assignment ended');

        $this->assertNotNull($divisionAssignment->fresh()->revoked_at);
        $this->assertNotNull($contestAssignment->fresh()->revoked_at);
    }

    public function test_assignment_matches_each_canonical_target_type(): void
    {
        [$creator, $event, $tabulator] = $this->eventWithTabulator();
        $judge = User::factory()->create(['email' => 'matching-judge@example.com']);
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);
        [$division, $siblingDivision] = $this->divisionsFor($event);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        $siblingContest = Contest::factory()->create(['competition_division_id' => $siblingDivision->getKey()]);
        $scorecard = EntryScorecard::factory()->create(['contest_id' => $contest->getKey()]);

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
        $scorecardAssignment = (new GrantScoringAssignment)->handle(
            $creator,
            $event,
            $judge,
            ScoringAssignmentScope::EntryScorecard,
            $scorecard,
        );

        $this->assertTrue($divisionAssignment->matches($division));
        $this->assertTrue($divisionAssignment->matches($contest));
        $this->assertFalse($divisionAssignment->matches($siblingContest));
        $this->assertTrue($contestAssignment->matches($contest));
        $this->assertFalse($contestAssignment->matches($division));
        $this->assertTrue($scorecardAssignment->matches($scorecard));
        $this->assertFalse($scorecardAssignment->matches($contest));
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
