<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Registrations\SaveDisciplineEntry;
use App\Enums\EntryStatus;
use App\Enums\EligibilityStatus;
use App\Enums\RosterMemberRole;
use App\Models\Competition;
use App\Models\Event;
use App\Models\EligibilityRecord;
use App\Models\Participant;
use App\Models\RosterMember;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workspace_exposes_division_scope_and_sport_rail(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail()->divisions()->where('slug', 'men')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.sports.tournament', [$event, $division]));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Sports/Tournament')
            ->where('event.id', (string) $event->getKey())
            ->has('sport.division_count')
            ->has('sport.entry_count')
            ->has('sport.player_count')
            ->where('division.id', (string) $division->getKey())
            ->has('division.entry_count')
            ->has('division.participating_entry_count')
            ->has('division.locked_entry_count')
            ->has('division.unlocked_entry_count')
            ->has('division.player_count')
            ->has('division.results_state')
            ->where('discipline', null)
            ->has('sports')
            ->has('blockers'));
    }

    public function test_workspace_blocks_generation_until_every_participating_entry_is_locked(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail()->divisions()->where('slug', 'men')->firstOrFail();

        $division->entries()->update(['status' => EntryStatus::Locked->value]);
        $division->entries()->orderBy('id')->firstOrFail()->update(['status' => EntryStatus::Active->value]);

        $response = $this->actingAs($admin)->get(route('admin.sports.tournament', [$event, $division]));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('can_generate', false)
            ->where('blockers', [
                'Approve every participating team sheet before making the draw.',
            ]));
    }

    public function test_discipline_workspace_is_contained_and_uses_discipline_scope(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'taekwondo')->firstOrFail()->divisions()->where('slug', 'men')->firstOrFail();
        $discipline = $division->disciplines()->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.sports.discipline-tournament', [$event, $division, $discipline]));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Sports/Tournament')
            ->where('discipline.id', (string) $discipline->getKey())
            ->where('discipline.family', 'combat')
            ->where('proposal.supported_bracket', true));
    }

    public function test_combat_discipline_entry_requires_a_locked_parent_and_eligible_starter(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'taekwondo')->firstOrFail()->divisions()->where('slug', 'men')->firstOrFail();
        $discipline = $division->disciplines()->firstOrFail();
        $entry = $division->entries()->firstOrFail();
        $participant = Participant::create([
            'event_id' => $event->getKey(),
            'event_delegation_id' => $entry->event_delegation_id,
            'display_name' => 'Starter Athlete',
            'is_active' => true,
        ]);
        RosterMember::create(['entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'role' => RosterMemberRole::StudentAthlete, 'is_active' => true]);
        EligibilityRecord::create(['event_id' => $event->getKey(), 'entry_id' => $entry->getKey(), 'participant_id' => $participant->getKey(), 'status' => EligibilityStatus::Eligible]);
        $entry->update(['status' => 'locked']);

        $disciplineEntry = (new SaveDisciplineEntry)->handle($admin, $event, $discipline, $entry, [['participant_id' => $participant->getKey(), 'is_starter' => true]], 'locked');

        $this->assertSame('locked', $disciplineEntry->fresh()->state);
        $this->assertDatabaseHas('discipline_entry_members', ['discipline_entry_id' => $disciplineEntry->getKey(), 'participant_id' => $participant->getKey(), 'is_starter' => true]);
    }

    /** @return array{0: \App\Models\User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'workspace@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event];
    }
}
