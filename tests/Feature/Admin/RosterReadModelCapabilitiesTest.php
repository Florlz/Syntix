<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Enums\TournamentFormat;
use App\Enums\TournamentState;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\User;
use App\Services\RosterReadModel;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterReadModelCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_capabilities_follow_roster_member_and_event_state_independently(): void
    {
        [$admin, $event] = $this->programme();
        $sport = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail();
        $division = $sport->divisions()->where('slug', 'men')->firstOrFail();
        $entry = $division->entries()->firstOrFail();
        $entry->update(['status' => EntryStatus::Active]);
        $participant = $this->participant($event, $entry->delegation, $admin);
        $member = $entry->rosterMembers()->create([
            'participant_id' => $participant->getKey(),
            'role' => RosterMemberRole::StudentAthlete,
            'is_active' => true,
        ]);

        $this->assertSame([
            'can_manage' => true,
            'can_edit_profile' => true,
            'can_edit_membership' => true,
            'can_restore_membership' => false,
            'can_record_exception' => false,
        ], $this->capabilities($event, $sport, $division, $entry->delegation, $participant));

        $member->update(['is_active' => false]);
        $this->assertSame([
            'can_manage' => true,
            'can_edit_profile' => true,
            'can_edit_membership' => true,
            'can_restore_membership' => true,
            'can_record_exception' => false,
        ], $this->capabilities($event, $sport, $division, $entry->delegation, $participant));

        $member->update(['is_active' => true]);
        $entry->update(['status' => EntryStatus::Locked]);
        $this->assertSame([
            'can_manage' => true,
            'can_edit_profile' => true,
            'can_edit_membership' => false,
            'can_restore_membership' => false,
            'can_record_exception' => true,
        ], $this->capabilities($event, $sport, $division, $entry->delegation, $participant));

        $entry->update(['status' => EntryStatus::Withdrawn]);
        $this->assertSame([
            'can_manage' => true,
            'can_edit_profile' => true,
            'can_edit_membership' => false,
            'can_restore_membership' => false,
            'can_record_exception' => false,
        ], $this->capabilities($event, $sport, $division, $entry->delegation, $participant));

        $entry->update(['status' => EntryStatus::Active]);
        $division->tournaments()->create([
            'competition_rule_version_id' => $division->governingRuleVersion()->firstOrFail()->getKey(),
            'format' => TournamentFormat::SingleElimination,
            'state' => TournamentState::Published,
            'eligible_entry_count' => 1,
            'created_by' => $admin->getKey(),
        ]);
        $this->assertTrue($this->capabilities($event, $sport, $division, $entry->delegation, $participant)['can_record_exception']);

        $member->update(['role' => RosterMemberRole::FacultyCoach]);
        $this->assertFalse($this->capabilities($event, $sport, $division, $entry->delegation, $participant)['can_record_exception']);

        $event->update(['state' => 'archived']);
        $this->assertSame([
            'can_manage' => false,
            'can_edit_profile' => false,
            'can_edit_membership' => false,
            'can_restore_membership' => false,
            'can_record_exception' => false,
        ], $this->capabilities($event, $sport, $division, $entry->delegation, $participant));
    }

    /** @return array<string, bool> */
    private function capabilities(Event $event, Competition $sport, Division $division, EventDelegation $delegation, Participant $participant): array
    {
        $workspace = app(RosterReadModel::class)->forDivision(
            $event->fresh(),
            $sport->fresh(),
            $division->fresh(),
            $delegation->fresh(),
        );

        return collect($workspace['selected']['participants'])
            ->firstWhere('id', (string) $participant->getKey())['capabilities'];
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Roster Capability Admin',
            'email' => 'roster-capabilities@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event->fresh()];
    }

    private function participant(Event $event, EventDelegation $delegation, User $admin): Participant
    {
        return Participant::query()->create([
            'event_id' => $event->getKey(),
            'event_delegation_id' => $delegation->getKey(),
            'display_name' => 'Capability Athlete',
            'student_number' => 'CAP-1',
            'student_number_normalized' => 'CAP-1',
            'is_active' => true,
            'is_competitor' => true,
            'created_by' => $admin->getKey(),
        ]);
    }
}
