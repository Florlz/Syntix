<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\Competition;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParticipantCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspect_reports_row_errors_and_resolves_common_headers(): void
    {
        [$admin, $event] = $this->programme();
        $department = $event->delegations()->where('is_active', true)->orderBy('id')->firstOrFail();
        $otherDepartment = $event->delegations()
            ->where('is_active', true)
            ->whereKeyNot($department->getKey())
            ->orderBy('id')
            ->firstOrFail();

        $existing = $this->participant($event, $department, 'Existing Player', 'CSV-001');
        $file = UploadedFile::fake()->createWithContent('players.csv', implode("\n", [
            'Department,Student ID,Full Name',
            $department->abbreviation.',CSV-002,New Player',
            $department->abbreviation.',csv-002,Duplicate Number',
            $otherDepartment->abbreviation.',CSV-001,Wrong Department',
        ]));

        $response = $this->actingAs($admin)->postJson(
            route('admin.participant-import.inspect', $event),
            ['file' => $file],
        );

        $response->assertOk()
            ->assertJsonPath('mapping.student_number', 'Student ID')
            ->assertJsonPath('mapping.display_name', 'Full Name')
            ->assertJsonPath('new_count', 1)
            ->assertJsonPath('existing_count', 0)
            ->assertJsonCount(2, 'errors');
        $this->assertDatabaseHas('participants', ['id' => $existing->getKey()]);
    }

    public function test_confirm_creates_profiles_without_memberships_and_reuses_same_department_profiles(): void
    {
        [$admin, $event] = $this->programme();
        $department = $event->delegations()->where('is_active', true)->orderBy('id')->firstOrFail();
        $existing = $this->participant($event, $department, 'Existing Player', 'CSV-010');
        $file = UploadedFile::fake()->createWithContent('players.csv', implode("\n", [
            'department_code,student_number,display_name,email',
            $department->abbreviation.',CSV-010,Existing Replacement,changed@example.test',
            $department->abbreviation.',CSV-011,Imported Player,imported@example.test',
        ]));

        $response = $this->actingAs($admin)->postJson(
            route('admin.participant-import.confirm', $event),
            ['file' => $file],
        );

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(2, 'selected_participant_ids');
        $this->assertSame(2, Participant::query()->where('event_id', $event->getKey())->count());
        $this->assertSame('Existing Player', $existing->fresh()->display_name);
        $this->assertDatabaseHas('participants', [
            'event_id' => $event->getKey(),
            'event_delegation_id' => $department->getKey(),
            'student_number_normalized' => 'CSV-011',
            'display_name' => 'Imported Player',
        ]);
        $this->assertDatabaseCount('entry_members', 0);
    }

    public function test_batch_membership_clears_players_for_the_selected_entry(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail()
            ->divisions()->where('slug', 'men')->firstOrFail();
        $entry = Entry::query()->where('competition_division_id', $division->getKey())->orderBy('id')->firstOrFail();
        $department = $entry->delegation;
        $first = $this->participant($event, $department, 'Batch One', 'BATCH-001');
        $second = $this->participant($event, $department, 'Batch Two', 'BATCH-002');

        $this->actingAs($admin)->put(route('admin.entry-members.batch', [$event, $entry]), [
            'members' => [
                ['participant_id' => $first->getKey(), 'role' => 'student_athlete'],
                ['participant_id' => $second->getKey(), 'role' => 'reserve'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $entry->rosterMembers()->where('is_active', true)->count());
        $this->assertSame(0, $entry->eligibilityRecords()->count());
    }

    public function test_batch_membership_rejects_a_stale_cross_department_selection_without_partial_changes(): void
    {
        [$admin, $event] = $this->programme();
        $entry = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail()
            ->divisions()->where('slug', 'men')->firstOrFail()->entries()->firstOrFail();
        $valid = $this->participant($event, $entry->delegation, 'Valid Batch Player', 'BATCH-VALID');
        $otherDepartment = $event->delegations()->whereKeyNot($entry->event_delegation_id)->firstOrFail();
        $stale = $this->participant($event, $otherDepartment, 'Stale Batch Player', 'BATCH-STALE');

        $this->actingAs($admin)->put(route('admin.entry-members.batch', [$event, $entry]), [
            'members' => [
                ['participant_id' => $valid->getKey(), 'role' => 'student_athlete'],
                ['participant_id' => $stale->getKey(), 'role' => 'reserve'],
            ],
        ])->assertSessionHasErrors('members');

        $this->assertDatabaseMissing('entry_members', ['entry_id' => $entry->getKey(), 'participant_id' => $valid->getKey()]);
        $this->assertDatabaseMissing('entry_members', ['entry_id' => $entry->getKey(), 'participant_id' => $stale->getKey()]);
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'CSV Admin',
            'email' => 'csv-'.uniqid().'@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event->fresh()];
    }

    private function participant(Event $event, EventDelegation $department, string $name, string $studentNumber): Participant
    {
        return Participant::query()->create([
            'event_id' => $event->getKey(),
            'event_delegation_id' => $department->getKey(),
            'display_name' => $name,
            'student_number' => $studentNumber,
            'student_number_normalized' => mb_strtoupper(trim($studentNumber)),
            'is_active' => true,
            'created_by' => $event->created_by,
        ]);
    }
}
