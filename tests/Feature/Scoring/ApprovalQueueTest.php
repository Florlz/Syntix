<?php

namespace Tests\Feature\Scoring;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapEventCreator;
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

    public function test_event_admin_sees_only_their_events_submitted_results(): void
    {
        $creator = (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB One']);
        $otherEvent = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB Two']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $otherAdmin = User::factory()->create(['email' => 'other-admin@example.com']);
        (new GrantEventRole)->handle($creator, $event, $admin, EventRole::Admin);
        (new GrantEventRole)->handle($creator, $otherEvent, $otherAdmin, EventRole::Admin);

        $submission = $this->submission($event->getKey(), $admin, 'Basketball final');
        $this->submission($otherEvent->getKey(), $otherAdmin, 'Other event final');

        $this->actingAs($admin)
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
        $creator = (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB '.uniqid()]);
        $admin = User::factory()->create();
        $judge = User::factory()->create();
        (new GrantEventRole)->handle($creator, $event, $admin, EventRole::Admin);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);

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
