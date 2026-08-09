<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\ResultSubmission;
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
