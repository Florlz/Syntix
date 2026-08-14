<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\CompetitionFormat;
use App\Enums\ParticipantMode;
use App\Enums\RuleVersionState;
use App\Enums\ScoringFamily;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_global_admin_can_access_private_registration_records_and_registration_creates_no_account(): void
    {
        [$admin, $event] = $this->programme();
        $delegation = $event->delegations()->firstOrFail();
        $worker = User::factory()->create();

        $this->get(route('admin.registrations.index', $event))->assertRedirect(route('login'));
        $this->actingAs($worker)->get(route('admin.registrations.index', $event))->assertForbidden();

        $userCount = User::query()->count();
        $this->actingAs($admin)->post(route('admin.participants.store', $event), [
            'event_delegation_id' => $delegation->getKey(),
            'display_name' => 'Maria Santos',
            'given_name' => 'Maria',
            'family_name' => 'Santos',
            'student_number' => ' 2025-AbC ',
            'email' => 'maria@example.test',
            'phone' => '09171234567',
            'private_notes' => 'Registrar verified.',
            'is_active' => true,
        ])->assertRedirect();

        $participant = Participant::query()->sole();
        $this->assertSame('2025-ABC', $participant->student_number_normalized);
        $this->assertSame($userCount, User::query()->count());

        $this->actingAs($admin)->get(route('admin.registrations.index', $event))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Registrations/Index')
                ->where('event.id', (string) $event->getKey())
                ->where('directory_summary.totals.players', 1)
                ->where('participants', [])
                ->where('sections', [])
                ->where('coach_sections', []));

        $this->get('/')->assertInertia(fn (Assert $page) => $page->missing('participants'));
    }

    public function test_student_numbers_are_unique_after_normalization_and_cross_event_writes_are_denied(): void
    {
        [$admin, $event] = $this->programme();
        $delegation = $event->delegations()->firstOrFail();
        $this->registerParticipant($admin, $event, $delegation, 'First Student', 'CSPC-001');

        $this->actingAs($admin)->post(route('admin.participants.store', $event), [
            'event_delegation_id' => $delegation->getKey(),
            'display_name' => 'Duplicate Student',
            'student_number' => ' cspc-001 ',
            'is_active' => true,
        ])->assertSessionHasErrors('student_number');
        $this->assertSame(1, Participant::query()->count());

        $otherEvent = Event::factory()->create(['created_by' => $admin->getKey()]);
        $otherDelegation = EventDelegation::factory()->create(['event_id' => $otherEvent->getKey()]);
        $this->actingAs($admin)->post(route('admin.participants.store', $event), [
            'event_delegation_id' => $otherDelegation->getKey(),
            'display_name' => 'Wrong Event',
            'student_number' => 'OTHER-1',
            'is_active' => true,
        ])->assertForbidden();

        $participant = Participant::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.participants.update', [$otherEvent, $participant]), [
            'event_delegation_id' => $otherDelegation->getKey(),
            'display_name' => 'Cross Event Edit',
            'is_active' => true,
        ])->assertForbidden();
        $this->assertSame('First Student', $participant->fresh()->display_name);
    }

    public function test_basketball_roster_and_coach_limits_are_transactional(): void
    {
        [$admin, $event] = $this->programme();
        $division = $this->division($event, 'basketball', 'men');
        $entry = $division->entries()->firstOrFail();
        $delegation = $entry->delegation;

        foreach (range(1, 15) as $number) {
            $participant = $this->participant($event, $delegation, "Athlete {$number}", "B-{$number}");
            $this->saveMember($admin, $event, $entry, $participant, 'student_athlete')->assertRedirect();
        }

        $sixteenth = $this->participant($event, $delegation, 'Athlete 16', 'B-16');
        $this->saveMember($admin, $event, $entry, $sixteenth, 'student_athlete')->assertSessionHasErrors('role');

        $studentCoach = $this->participant($event, $delegation, 'Student Coach', 'B-SC-1');
        $this->saveMember($admin, $event, $entry, $studentCoach, 'student_coach')->assertRedirect();
        $secondStudentCoach = $this->participant($event, $delegation, 'Student Coach 2', 'B-SC-2');
        $this->saveMember($admin, $event, $entry, $secondStudentCoach, 'student_coach')->assertSessionHasErrors('role');

        foreach (range(1, 2) as $number) {
            $coach = $this->participant($event, $delegation, "Faculty Coach {$number}", "B-FC-{$number}");
            $this->saveMember($admin, $event, $entry, $coach, 'faculty_coach')->assertRedirect();
        }
        $thirdFacultyCoach = $this->participant($event, $delegation, 'Faculty Coach 3', 'B-FC-3');
        $this->saveMember($admin, $event, $entry, $thirdFacultyCoach, 'faculty_coach')->assertSessionHasErrors('role');

        $this->assertSame(18, $entry->rosterMembers()->where('is_active', true)->count());
        $this->assertDatabaseMissing('entry_members', ['participant_id' => $sixteenth->getKey()]);
        $this->assertDatabaseMissing('entry_members', ['participant_id' => $secondStudentCoach->getKey()]);
        $this->assertDatabaseMissing('entry_members', ['participant_id' => $thirdFacultyCoach->getKey()]);

        $otherDelegation = $event->delegations()->whereKeyNot($delegation->getKey())->firstOrFail();
        $outsider = $this->participant($event, $otherDelegation, 'Other Team Athlete', 'B-OTHER');
        $this->saveMember($admin, $event, $entry, $outsider, 'reserve')->assertForbidden();
    }

    public function test_individual_pair_and_relay_entries_enforce_their_governing_limits(): void
    {
        [$admin, $event] = $this->programme();
        $delegation = $event->delegations()->firstOrFail();

        $individual = $this->division($event, 'extemporaneous-speaking', 'open')->entries()
            ->where('event_delegation_id', $delegation->getKey())->firstOrFail();
        $this->saveMember($admin, $event, $individual, $this->participant($event, $delegation, 'Solo One', 'I-1'), 'student_athlete')->assertRedirect();
        $this->saveMember($admin, $event, $individual, $this->participant($event, $delegation, 'Solo Two', 'I-2'), 'student_athlete')->assertSessionHasErrors('role');

        $pair = $this->division($event, 'vocal-duet', 'open')->entries()
            ->where('event_delegation_id', $delegation->getKey())->firstOrFail();
        $this->saveMember($admin, $event, $pair, $this->participant($event, $delegation, 'Duet One', 'P-1'), 'student_athlete')->assertRedirect();
        $this->saveMember($admin, $event, $pair, $this->participant($event, $delegation, 'Duet Two', 'P-2'), 'student_athlete')->assertRedirect();
        $this->saveMember($admin, $event, $pair, $this->participant($event, $delegation, 'Duet Three', 'P-3'), 'student_athlete')->assertSessionHasErrors('role');

        $relayDivision = $this->relayDivision($event, $admin);
        $relay = Entry::query()->create([
            'competition_division_id' => $relayDivision->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'name' => 'Test Relay',
            'entry_mode' => ParticipantMode::Relay,
            'status' => 'active',
        ]);
        foreach (range(1, 4) as $number) {
            $this->saveMember($admin, $event, $relay, $this->participant($event, $delegation, "Relay {$number}", "R-{$number}"), 'student_athlete')->assertRedirect();
        }
        $this->saveMember($admin, $event, $relay, $this->participant($event, $delegation, 'Relay Five', 'R-5'), 'student_athlete')->assertSessionHasErrors('role');
    }

    public function test_eligibility_lock_unlock_and_published_withdrawal_preserve_history(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->division($event, 'basketball', 'women')->entries()->firstOrFail();
        $participant = $this->participant($event, $entry->delegation, 'Lockable Athlete', 'LOCK-1');
        $this->saveMember($admin, $event, $entry, $participant, 'student_athlete')->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.participants.update', [$event, $participant]), [
            'event_delegation_id' => $participant->event_delegation_id,
            'display_name' => $participant->display_name,
            'student_number' => $participant->student_number,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($participant->fresh()->is_active);

        $otherDelegation = $event->delegations()->whereKeyNot($entry->event_delegation_id)->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.participants.update', [$event, $participant]), [
            'event_delegation_id' => $otherDelegation->getKey(),
            'display_name' => $participant->display_name,
            'student_number' => $participant->student_number,
            'is_active' => true,
        ])->assertSessionHasErrors('event_delegation_id');
        $this->assertSame($entry->event_delegation_id, $participant->fresh()->event_delegation_id);

        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'eligible',
            'reason' => null,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.entries.status', [$event, $entry]), [
            'status' => 'locked', 'roster_review_confirmed' => true,
            'reason' => null,
        ])->assertRedirect();
        $this->assertSame('locked', $entry->fresh()->entryStatus()->value);

        $this->saveMember($admin, $event, $entry->fresh(), $participant, 'reserve')->assertSessionHasErrors('entry');
        $this->actingAs($admin)->patch(route('admin.entries.status', [$event, $entry]), [
            'status' => 'active',
            'reason' => null,
        ])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.entries.status', [$event, $entry]), [
            'status' => 'active',
            'reason' => 'Correcting the submitted roster.',
        ])->assertRedirect();

        $rule = $entry->division->governingRuleVersion;
        $tournament = Tournament::query()->create([
            'competition_division_id' => $entry->competition_division_id,
            'competition_rule_version_id' => $rule->getKey(),
            'format' => CompetitionFormat::SingleElimination,
            'state' => TournamentState::Published,
            'eligible_entry_count' => 1,
            'created_by' => $admin->getKey(),
            'draw_locked_at' => now(),
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'withdrawn',
            'reason' => null,
        ])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->put(route('admin.eligibility.update', [$event, $entry, $participant]), [
            'status' => 'withdrawn',
            'reason' => 'Medical withdrawal confirmed by the committee.',
        ])->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->getKey(), 'state' => 'published']);
        $this->assertDatabaseHas('entry_members', ['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'is_active' => false]);
        $this->assertDatabaseHas('eligibility_records', ['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'status' => 'withdrawn']);
        $this->assertDatabaseHas('audit_logs', ['event_id' => $event->getKey(), 'action' => 'eligibility.set']);
    }

    public function test_active_membership_can_be_approved_without_per_player_eligibility(): void
    {
        [$admin, $event] = $this->programme();
        $entry = $this->division($event, 'basketball', 'women')->entries()->firstOrFail();
        $participant = $this->participant($event, $entry->delegation, 'Pending Lock Player', 'LOCK-PENDING');
        $this->saveMember($admin, $event, $entry, $participant, 'student_athlete')->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.entries.status', [$event, $entry]), [
            'status' => 'locked', 'reason' => null, 'roster_review_confirmed' => true,
        ])->assertRedirect(route('admin.sports.show', [
            'event' => $event,
            'sport' => $entry->division->competition,
            'tab' => 'rosters',
            'division' => $entry->competition_division_id,
            'department' => $entry->event_delegation_id,
        ]));
        $this->assertSame('locked', $entry->fresh()->entryStatus()->value);
        $this->assertDatabaseHas('roster_approvals', ['entry_id' => $entry->getKey(), 'revision' => 1]);
    }

    public function test_filters_are_reflected_in_url_state_and_no_registration_delete_route_exists(): void
    {
        [$admin, $event] = $this->programme();
        $delegation = $event->delegations()->firstOrFail();
        $participant = $this->participant($event, $delegation, 'Searchable Person', 'SEARCH-1');

        $this->actingAs($admin)->get(route('admin.registrations.index', $event).'?q=Searchable&delegation='.$delegation->getKey().'&roster_status=unassigned')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'Searchable')
                ->where('filters.delegation', (string) $delegation->getKey())
                ->where('filters.roster_status', 'unassigned')
                ->where('directory_summary.totals.unassigned', 1)
                ->where('participants', []));

        $deleteRegistrationRoutes = collect(Route::getRoutes())->filter(function ($route): bool {
            return in_array('DELETE', $route->methods(), true)
                && (str_contains($route->uri(), 'participants') || str_contains($route->uri(), 'entries'));
        });
        $this->assertCount(0, $deleteRegistrationRoutes);
    }

    public function test_directory_segments_players_by_department_sport_division_and_roster_state(): void
    {
        [$admin, $event] = $this->programme();
        $division = $this->division($event, 'basketball', 'men');
        $entry = $division->entries()->firstOrFail();
        $player = $this->participant($event, $entry->delegation, 'Segmented Player', 'SEGMENT-1');
        $this->saveMember($admin, $event, $entry, $player, 'student_athlete')->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.registrations.index', $event).'?view=players&directory_department='.$entry->event_delegation_id.'&directory_sport='.$division->competition_id.'&directory_division='.$division->getKey().'&directory_roster=assigned')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.directory_department', (string) $entry->event_delegation_id)
                ->where('filters.directory_sport', (string) $division->competition_id)
                ->where('filters.directory_division', (string) $division->getKey())
                ->where('filters.directory_roster', 'assigned')
                ->where('selection.department', (string) $entry->event_delegation_id)
                ->where('selection.sport', (string) $division->competition_id)
                ->where('selection.division', (string) $division->getKey())
                ->has('directory_summary.departments'));

        $this->actingAs($admin)
            ->get(route('admin.registrations.directory-preview', $event).'?view=players&department='.$entry->event_delegation_id.'&sport='.$division->competition_id.'&division='.$division->getKey().'&entry='.$entry->getKey())
            ->assertOk()
            ->assertJsonPath('people.0.id', (string) $player->getKey())
            ->assertJsonPath('people.0.display_name', 'Segmented Player')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('limit', 25);
    }

    public function test_directory_segments_coaches_by_sport_assignment(): void
    {
        [$admin, $event] = $this->programme();
        $division = $this->division($event, 'basketball', 'men');
        $coach = $this->participant($event, $division->entries()->firstOrFail()->delegation, 'Segmented Coach', 'SEGMENT-COACH');

        $this->actingAs($admin)->post(route('admin.coach-assignments.store', [$event, $coach]), [
            'coach_type' => 'faculty_coach',
            'title' => 'Head Coach',
            'scope_type' => 'competition_division',
            'scope_key' => (string) $division->getKey(),
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.registrations.index', $event).'?view=coaches&directory_sport='.$division->competition_id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'coaches')
                ->where('filters.directory_sport', (string) $division->competition_id)
                ->where('selection.sport', (string) $division->competition_id)
                ->where('directory_summary.totals.coaches', 1));

        $this->actingAs($admin)
            ->get(route('admin.registrations.directory-preview', $event).'?view=coaches&department='.$coach->event_delegation_id.'&sport='.$division->competition_id.'&division='.$division->getKey().'&entry='.$division->entries()->firstOrFail()->getKey())
            ->assertOk()
            ->assertJsonPath('people.0.id', (string) $coach->getKey())
            ->assertJsonPath('total', 1);
    }

    public function test_sport_scoped_registration_url_redirects_to_the_canonical_roster_workspace(): void
    {
        [$admin, $event] = $this->programme();
        $division = $this->division($event, 'basketball', 'men');
        $entry = $division->entries()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.registrations.index', $event).'?competition='.$division->competition_id.'&division='.$division->getKey().'&entry='.$entry->getKey())
            ->assertRedirect(route('admin.sports.show', [
                'event' => $event,
                'sport' => $division->competition_id,
                'tab' => 'rosters',
                'division' => $division->getKey(),
                'department' => $entry->event_delegation_id,
            ]));
    }

    public function test_cross_event_entry_cannot_be_used_in_a_scoped_registration_redirect(): void
    {
        [$admin, $event] = $this->programme();
        $otherEvent = Event::factory()->create();
        $otherCompetition = Competition::factory()->for($otherEvent)->create();
        $otherDivision = Division::factory()->for($otherCompetition)->create();
        $otherDepartment = EventDelegation::factory()->for($otherEvent)->create();
        $otherEntry = Entry::query()->create([
            'competition_division_id' => $otherDivision->getKey(),
            'event_delegation_id' => $otherDepartment->getKey(),
            'name' => 'Other Entry',
            'entry_mode' => 'team',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.registrations.index', $event).'?entry='.$otherEntry->getKey())
            ->assertNotFound();
    }

    public function test_department_directory_and_roster_pages_use_the_department_first_flow(): void
    {
        [$admin, $event] = $this->programme();
        $department = $event->delegations()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.departments.index', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Departments/Index')
                ->where('event.id', (string) $event->getKey())
                ->has('directory_summary.departments')
                ->has('directory_summary.totals'));

        $this->actingAs($admin)
            ->get(route('admin.departments.show', [$event, $department]).'?view=coaches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Departments/Show')
                ->where('department.id', (string) $department->getKey())
                ->where('department.name', $department->name)
                ->where('initial_view', 'coaches')
                ->has('department.sports')
                ->has('department.sports.0.divisions')
                ->has('departments')
                ->has('competitions'));
    }

    public function test_department_roster_page_rejects_a_department_from_another_event(): void
    {
        [$admin, $event] = $this->programme();
        $otherEvent = Event::factory()->create(['created_by' => $admin->getKey()]);
        $otherDepartment = EventDelegation::factory()->for($otherEvent)->create();

        $this->actingAs($admin)
            ->get(route('admin.departments.show', [$event, $otherDepartment]))
            ->assertNotFound();
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event->fresh()];
    }

    private function division(Event $event, string $competitionSlug, string $divisionSlug): Division
    {
        return Competition::query()->whereBelongsTo($event)->where('slug', $competitionSlug)
            ->firstOrFail()->divisions()->where('slug', $divisionSlug)->firstOrFail();
    }

    private function participant(Event $event, EventDelegation $delegation, string $name, string $studentNumber): Participant
    {
        return Participant::query()->create([
            'event_id' => $event->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'display_name' => $name,
            'student_number' => $studentNumber,
            'student_number_normalized' => mb_strtoupper(trim($studentNumber)),
            'is_active' => true,
            'created_by' => $event->created_by,
        ]);
    }

    private function registerParticipant(User $admin, Event $event, EventDelegation $delegation, string $name, string $studentNumber): void
    {
        $this->actingAs($admin)->post(route('admin.participants.store', $event), [
            'event_delegation_id' => $delegation->getKey(),
            'display_name' => $name,
            'student_number' => $studentNumber,
            'is_active' => true,
        ])->assertRedirect();
    }

    private function saveMember(User $admin, Event $event, Entry $entry, Participant $participant, string $role)
    {
        return $this->actingAs($admin)->put(route('admin.entry-members.update', [$event, $entry, $participant]), [
            'role' => $role,
            'is_active' => true,
            'notes' => null,
        ]);
    }

    private function relayDivision(Event $event, User $admin): Division
    {
        $competition = Competition::query()->create([
            'event_id' => $event->getKey(),
            'name' => 'Test Relay',
            'slug' => 'test-relay',
        ]);
        $division = $competition->divisions()->create(['name' => 'Open', 'slug' => 'open']);
        CompetitionRuleVersion::query()->create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => RuleVersionState::ActivatedEditable,
            'is_governing' => true,
            'scoring_family' => ScoringFamily::Objective,
            'format' => CompetitionFormat::Placement,
            'participant_mode' => ParticipantMode::Relay,
            'min_roster_size' => 4,
            'max_roster_size' => 4,
            'created_by' => $admin->getKey(),
        ]);

        return $division->fresh('governingRuleVersion');
    }
}
