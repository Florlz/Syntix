<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\CompetitionFormat;
use App\Enums\EligibilityStatus;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Database\Seeders\SiklabReferenceSeeder;
use Tests\TestCase;

class RosterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_atomic_player_update_preserves_eligibility_and_returns_canonical_participants(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->entry($event, 'basketball', 'men');
        $participant = $this->participant($event, $entry->delegation, 'Roster Athlete', 'RI-1');

        $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $participant]), [
            'role' => 'student_athlete', 'is_active' => true, 'notes' => null,
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'eligible', 'reason' => null,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'profile' => ['display_name' => 'Corrected Athlete'],
            'membership' => ['role' => 'reserve', 'is_active' => true, 'notes' => 'Role corrected'],
        ])->assertRedirect();

        $this->assertSame('Corrected Athlete', $participant->fresh()->display_name);
        $this->assertSame('reserve', $entry->rosterMembers()->where('participant_id', $participant->getKey())->firstOrFail()->roleType()->value);
        $this->assertSame('eligible', $entry->eligibilityRecords()->where('participant_id', $participant->getKey())->firstOrFail()->eligibilityStatus()->value);

        $this->actingAs($admin)->get(route('admin.sports.show', [
            'event' => $event,
            'sport' => $entry->division->competition,
            'tab' => 'rosters',
            'division' => $entry->competition_division_id,
            'department' => $entry->event_delegation_id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('roster_workspace.selected.participants.0.id', (string) $participant->getKey())
            ->where('roster_workspace.selected.participants.0.display_name', 'Corrected Athlete')
            ->where('roster_workspace.selected.participants.0.membership.role', 'reserve')
            ->where('roster_workspace.selected.counts.active_players', 1));
    }

    public function test_coaches_are_returned_without_eligibility_controls(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->entry($event, 'basketball', 'women');
        $coach = $this->participant($event, $entry->delegation, 'Team Coach', 'RI-C');

        $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $coach]), [
            'role' => 'faculty_coach', 'is_active' => true, 'notes' => null,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $coach]), [
            'eligibility' => ['status' => 'eligible'],
        ])->assertSessionHasErrors('eligibility.status');
        $this->assertDatabaseMissing('eligibility_records', ['entry_id' => $entry->getKey(), 'participant_id' => $coach->getKey()]);
    }

    public function test_adverse_restore_requires_a_new_status_and_rolls_back_membership_restore(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->entry($event, 'basketball', 'men');
        $participant = $this->participant($event, $entry->delegation, 'Adverse Athlete', 'RI-A');

        $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $participant]), [
            'role' => 'student_athlete', 'is_active' => true, 'notes' => null,
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'withdrawn', 'reason' => 'Medical hold',
        ])->assertRedirect();
        $this->assertDatabaseHas('entry_members', [
            'entry_id' => $entry->getKey(),
            'participant_id' => $participant->getKey(),
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('eligibility_records', [
            'entry_id' => $entry->getKey(),
            'participant_id' => $participant->getKey(),
            'status' => 'withdrawn',
        ]);

        $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $participant]), [
            'role' => 'student_athlete', 'is_active' => true, 'notes' => null,
        ])->assertSessionHasErrors('eligibility.status');

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'membership' => ['is_active' => true],
        ])->assertSessionHasErrors('eligibility.status');
        $this->assertDatabaseHas('entry_members', ['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'is_active' => false]);

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'membership' => ['is_active' => true],
            'eligibility' => ['status' => 'pending'],
        ])->assertRedirect();
        $this->assertDatabaseHas('entry_members', ['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'is_active' => true]);
        $this->assertDatabaseHas('eligibility_records', ['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'status' => 'pending']);
    }

    public function test_archived_roster_player_update_is_rejected(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->entry($event, 'basketball', 'men');
        $participant = $this->participant($event, $entry->delegation, 'Archived Athlete', 'RI-X');
        $event->update(['state' => 'archived']);

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'profile' => ['display_name' => 'Should Not Save'],
        ])->assertForbidden();
        $this->assertSame('Archived Athlete', $participant->fresh()->display_name);
    }

    public function test_locked_roster_keeps_membership_fixed_but_allows_adverse_correction(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->entry($event, 'basketball', 'women');
        $participant = $this->participant($event, $entry->delegation, 'Locked Athlete', 'RI-L');

        $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $participant]), [
            'role' => 'student_athlete', 'is_active' => true, 'notes' => null,
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'eligible', 'reason' => null,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.entries.status', [$event, $entry]), [
            'status' => 'locked', 'reason' => null, 'roster_review_confirmed' => true,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'membership' => ['role' => 'reserve', 'is_active' => true],
        ])->assertSessionHasErrors('entry');

        $this->actingAs($admin)->put(route('admin.roster-players.update', [$event, $entry, $participant]), [
            'eligibility' => ['status' => 'withdrawn', 'reason' => 'Eligibility correction recorded.'],
        ])->assertRedirect();
        $this->assertDatabaseHas('entry_members', [
            'entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'is_active' => false,
        ]);
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Roster Admin', 'email' => 'roster-'.uniqid().'@example.test', 'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event->fresh()];
    }

    private function entry(Event $event, string $competitionSlug, string $divisionSlug): Entry
    {
        return Competition::query()->whereBelongsTo($event)->where('slug', $competitionSlug)->firstOrFail()
            ->divisions()->where('slug', $divisionSlug)->firstOrFail()->entries()->firstOrFail();
    }

    private function participant(Event $event, EventDelegation $delegation, string $name, string $studentNumber): Participant
    {
        return Participant::query()->create([
            'event_id' => $event->getKey(), 'event_delegation_id' => $delegation->getKey(), 'display_name' => $name,
            'student_number' => $studentNumber, 'student_number_normalized' => mb_strtoupper($studentNumber),
            'is_active' => true, 'created_by' => $event->created_by,
        ]);
    }
}
