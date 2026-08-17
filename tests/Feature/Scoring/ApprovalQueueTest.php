<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Entry;
use App\Models\EventDelegation;
use App\Models\ResultSubmission;
use App\Enums\ParticipantMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_sees_only_the_selected_events_submitted_results(): void
    {
        $creator = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB One']);
        $otherEvent = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB Two']);
        $submission = $this->submission($event->getKey(), $creator, 'Basketball final');
        $this->submission($otherEvent->getKey(), $creator, 'Other event final');

        $this->actingAs($creator)
            ->get(route('admin.approvals.index', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Approvals/Index')
                ->has('result_submissions', 1)
                ->where('result_submissions.0.id', (string) $submission->getKey())
                ->has('division_placements', 0));
    }

    public function test_non_admin_cannot_open_the_approval_queue(): void
    {
        $creator = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB '.uniqid()]);
        $judge = User::factory()->create();
        (new GrantEventRole)->handle($creator, $event, $judge, EventRole::Judge);

        $this->actingAs($judge)
            ->get(route('admin.approvals.index', $event))
            ->assertForbidden();
    }

    public function test_match_queue_resolves_named_sides_and_respects_sport_filters(): void
    {
        $creator = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'named-results-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB Named Results']);
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Basketball']);
        $division = Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => 'Men']);
        $homeDelegation = EventDelegation::factory()->create(['event_id' => $event->getKey(), 'name' => 'Home Department']);
        $awayDelegation = EventDelegation::factory()->create(['event_id' => $event->getKey(), 'name' => 'Away Department']);
        $home = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $homeDelegation->getKey(),
            'name' => 'Home Five',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $away = Entry::create([
            'competition_division_id' => $division->getKey(),
            'event_delegation_id' => $awayDelegation->getKey(),
            'name' => 'Away Five',
            'entry_mode' => ParticipantMode::Team,
            'status' => 'active',
        ]);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey(), 'name' => 'Final']);
        $contest->entries()->createMany([
            ['entry_id' => $home->getKey(), 'slot' => 1],
            ['entry_id' => $away->getKey(), 'slot' => 2],
        ]);
        $submission = ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $creator->getKey(),
            'state' => 'submitted',
            'contest_revision' => 2,
            'payload' => [
                'outcome_type' => 'played',
                'home' => 81,
                'away' => 76,
                'winner_entry_id' => $home->getKey(),
                'result' => 'home_win',
            ],
            'submitted_at' => now(),
        ]);

        $this->actingAs($creator)
            ->get(route('admin.approvals.index', $event).'?competition='.$competition->getKey().'&division='.$division->getKey())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.competition', 'Basketball')
                ->where('scope.division', 'Men')
                ->where('workspace.sport.id', (string) $competition->getKey())
                ->has('workspace.divisions', 1)
                ->where('workspace.divisions.0.id', (string) $division->getKey())
                ->where('result_submissions.0.id', (string) $submission->getKey())
                ->where('result_submissions.0.home.name', 'Home Five')
                ->where('result_submissions.0.home.score', 81)
                ->where('result_submissions.0.away.name', 'Away Five')
                ->where('result_submissions.0.winner', 'Home Five')
                ->missing('result_submissions.0.payload'));
    }

    private function submission(int $eventId, User $submitter, string $name): ResultSubmission
    {
        $competition = Competition::factory()->create(['event_id' => $eventId]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);
        $contest = Contest::factory()->create([
            'competition_division_id' => $division->getKey(),
            'name' => $name,
            'revision' => 3,
        ]);

        return ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $submitter->getKey(),
            'state' => 'submitted',
            'contest_revision' => 3,
            'payload' => ['outcome_type' => 'played'],
            'submitted_at' => now(),
        ]);
    }
}
