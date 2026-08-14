<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\CoachAssignment;
use App\Models\CoachCapacityRule;
use App\Models\Competition;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterCoachSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_atomically_creates_department_support_profile_and_current_division_assignment(): void
    {
        [$admin, $event, $sport, $division, $department] = $this->context();

        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $department]), $this->payload())
            ->assertRedirect(route('admin.sports.show', [
                'event' => $event, 'sport' => $sport, 'tab' => 'rosters',
                'division' => $division, 'department' => $department,
            ]));

        $participant = Participant::query()->where('display_name', 'Jamie Support')->sole();
        $this->assertSame((int) $department->getKey(), (int) $participant->event_delegation_id);
        $this->assertFalse((bool) $participant->is_competitor);
        $this->assertDatabaseHas('coach_assignments', [
            'participant_id' => $participant->getKey(),
            'event_delegation_id' => $department->getKey(),
            'scope_type' => 'competition_division',
            'scope_key' => (string) $division->getKey(),
            'coach_type' => 'student_coach',
            'title' => 'Trainer',
            'is_active' => true,
        ]);
    }

    public function test_foreign_or_inactive_department_scope_is_rejected_without_creation(): void
    {
        [$admin, $event, , $division] = $this->context();
        $foreignEvent = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $foreignEvent);
        $foreignDepartment = $foreignEvent->delegations()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $foreignDepartment]), $this->payload())->assertNotFound();
        $this->assertDatabaseMissing('participants', ['event_id' => $event->getKey(), 'display_name' => 'Jamie Support']);

        $localDepartment = $event->delegations()->firstOrFail();
        $localDepartment->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $localDepartment]), $this->payload())->assertNotFound();
        $this->assertDatabaseMissing('participants', ['event_id' => $event->getKey(), 'display_name' => 'Jamie Support']);
        $this->assertNotSame((int) $event->getKey(), (int) $foreignEvent->getKey());
    }

    public function test_archived_event_and_non_admin_are_rejected(): void
    {
        [$admin, $event, , $division, $department] = $this->context();
        $worker = User::factory()->create();

        $this->actingAs($worker)->post(route('admin.roster-coach-support.store', [$event, $division, $department]), $this->payload())->assertForbidden();
        $event->update(['state' => 'archived', 'archived_at' => now()]);
        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $department]), $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('participants', ['event_id' => $event->getKey(), 'display_name' => 'Jamie Support']);
    }

    public function test_validation_or_assignment_failure_rolls_back_the_new_participant(): void
    {
        [$admin, $event, , $division, $department] = $this->context();

        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $department]), [
            ...$this->payload(), 'display_name' => '',
        ])->assertSessionHasErrors('display_name');
        $this->assertDatabaseCount('participants', 0);

        CoachCapacityRule::query()->create([
            'event_id' => $event->getKey(), 'scope_type' => 'competition_division',
            'scope_key' => (string) $division->getKey(), 'student_coach_max' => 0,
        ]);
        $this->actingAs($admin)->post(route('admin.roster-coach-support.store', [$event, $division, $department]), $this->payload())
            ->assertSessionHasErrors('coach_type');
        $this->assertDatabaseMissing('participants', ['event_id' => $event->getKey(), 'display_name' => 'Jamie Support']);
        $this->assertDatabaseCount('coach_assignments', 0);
    }

    private function payload(): array
    {
        return [
            'display_name' => 'Jamie Support', 'given_name' => 'Jamie', 'family_name' => 'Support',
            'student_number' => 'SUP-1', 'email' => 'jamie@example.test', 'phone' => '09171234567',
            'private_notes' => 'Created from roster.', 'coach_type' => 'student_coach',
            'title' => 'Trainer', 'notes' => 'Current team coverage.',
        ];
    }

    private function context(string $suffix = ''): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Roster Coach Admin',
            'email' => 'roster-coach-'.$suffix.uniqid().'@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);
        $sport = Competition::query()->whereBelongsTo($event)->where('slug', 'arnis')->firstOrFail();
        $division = $sport->divisions()->where('slug', 'men')->firstOrFail();
        $department = $event->delegations()->where('is_active', true)->firstOrFail();

        return [$admin, $event, $sport, $division, $department];
    }
}
