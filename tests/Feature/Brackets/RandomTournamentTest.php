<?php

namespace Tests\Feature\Brackets;

use App\Actions\Brackets\GenerateRandomTournament;
use App\Actions\Brackets\PublishBracket;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Registrations\SaveDisciplineEntry;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Models\Competition;
use App\Models\ContestEntry;
use App\Models\Discipline;
use App\Models\DisciplineEntry;
use App\Models\Division;
use App\Models\EligibilityRecord;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RosterMember;
use App\Models\User;
use App\Support\SeededDraw;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RandomTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_draw_is_reproducible_idempotent_and_redrawable_only_before_publication(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'basketball')
            ->firstOrFail()
            ->divisions()
            ->where('slug', 'men')
            ->firstOrFail();
        $division->entries()->update(['status' => 'locked']);
        $generate = new GenerateRandomTournament;
        $command = (string) Str::uuid();

        $first = $generate->handle($admin, $division, $command);
        $replay = $generate->handle($admin, $division, $command);
        $record = $first->drawRecords()->firstOrFail();
        $eligible = $division->entries()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertTrue($first->is($replay));
        $this->assertSame(1, $division->tournaments()->count());
        $this->assertSame('cryptographic_random', $record->source);
        $this->assertSame(SeededDraw::ALGORITHM_VERSION, $record->algorithm_version);
        $this->assertSame(SeededDraw::shuffle($eligible, $record->random_seed), $record->draw_order);
        $this->assertSame($command, $record->command_uuid);

        $replacement = $generate->handle($admin, $division, (string) Str::uuid(), true);
        $this->assertFalse($first->is($replacement));
        $this->assertDatabaseHas('tournaments', ['id' => $first->getKey(), 'state' => 'archived']);
        $this->assertDatabaseHas('bracket_versions', [
            'tournament_id' => $first->getKey(),
            'state' => 'replaced',
        ]);

        $bracket = $replacement->bracketVersions()->firstOrFail();
        (new PublishBracket)->handle($admin, $bracket);
        $this->assertGreaterThan(0, ContestEntry::query()->count());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('published tournament');
        $generate->handle($admin, $division, (string) Str::uuid(), true);
    }

    public function test_seven_departments_generate_the_proposal_double_elimination_route(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'badminton')
            ->firstOrFail()
            ->divisions()
            ->where('slug', 'women')
            ->firstOrFail();
        $division->entries()->update(['status' => 'locked']);

        $tournament = (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid());
        $bracket = $tournament->bracketVersions()->firstOrFail();

        $this->assertSame(15, $bracket->nodes()->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'bye')->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'reset_final')->count());
    }

    public function test_random_draw_requires_every_participating_entry_to_be_locked(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'basketball')
            ->firstOrFail()
            ->divisions()
            ->where('slug', 'men')
            ->firstOrFail();
        $division->entries()->update(['status' => 'locked']);
        $division->entries()->orderBy('id')->firstOrFail()->update(['status' => 'active']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approve every participating team sheet before making the draw.');

        (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid());
    }

    public function test_withdrawn_entries_are_excluded_from_a_random_draw(): void
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'basketball')
            ->firstOrFail()
            ->divisions()
            ->where('slug', 'men')
            ->firstOrFail();
        $division->entries()->update(['status' => EntryStatus::Locked->value]);
        $withdrawn = $division->entries()->orderBy('id')->firstOrFail();
        $withdrawn->update(['status' => EntryStatus::Withdrawn->value]);

        $tournament = (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid());
        $drawOrder = $tournament->drawRecords()->firstOrFail()->draw_order;

        $this->assertSame($division->entries()->count() - 1, $tournament->eligible_entry_count);
        $this->assertNotContains((int) $withdrawn->getKey(), $drawOrder);
    }

    public function test_discipline_random_draw_requires_a_locked_assignment(): void
    {
        [$admin, $event, $division, $discipline, $entry, $disciplineEntry] = $this->disciplineContext();
        $disciplineEntry->update(['state' => 'draft']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Approve every team's team sheet and lineup for this event before making the draw.");

        (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid(), false, $discipline);
    }

    public function test_discipline_random_draw_requires_a_locked_parent_entry(): void
    {
        [$admin, $event, $division, $discipline, $entry] = $this->disciplineContext();
        $entry->update(['status' => EntryStatus::Active->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Approve this team's team sheet before approving its lineup for this event.");

        (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid(), false, $discipline);
    }

    public function test_discipline_random_draw_excludes_withdrawn_teams_even_when_their_lineup_is_approved(): void
    {
        [$admin, $event, $division, $discipline, $readyEntry] = $this->disciplineContext();
        $withdrawnEntry = $division->entries()->whereKeyNot($readyEntry->getKey())->orderBy('id')->firstOrFail();
        $this->lockEventLineup($admin, $event, $discipline, $withdrawnEntry, 'Withdrawn Lineup Athlete');
        $withdrawnEntry->update(['status' => EntryStatus::Withdrawn->value]);

        $tournament = (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid(), false, $discipline);

        $this->assertSame('uncontested', $tournament->tournamentState()->value);
        $this->assertSame(1, $tournament->eligible_entry_count);
        $this->assertNotContains((int) $withdrawnEntry->getKey(), $tournament->drawRecords()->firstOrFail()->draw_order);
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event];
    }

    /** @return array{0: User, 1: Event, 2: Division, 3: Discipline, 4: Entry, 5: DisciplineEntry} */
    private function disciplineContext(): array
    {
        [$admin, $event] = $this->programme();
        $division = Competition::query()->whereBelongsTo($event)->where('slug', 'taekwondo')->firstOrFail()->divisions()->where('slug', 'men')->firstOrFail();
        $discipline = $division->disciplines()->firstOrFail();
        $entry = $division->entries()->firstOrFail();
        $disciplineEntry = $this->lockEventLineup($admin, $event, $discipline, $entry, 'Starter Athlete');

        return [$admin, $event, $division, $discipline, $entry, $disciplineEntry];
    }

    private function lockEventLineup(User $admin, Event $event, Discipline $discipline, Entry $entry, string $name): DisciplineEntry
    {
        $participant = Participant::create([
            'event_id' => $event->getKey(),
            'event_delegation_id' => $entry->event_delegation_id,
            'display_name' => $name,
            'is_active' => true,
        ]);
        RosterMember::create([
            'entry_id' => $entry->getKey(),
            'participant_id' => $participant->getKey(),
            'role' => RosterMemberRole::StudentAthlete,
            'is_active' => true,
        ]);
        EligibilityRecord::create([
            'event_id' => $event->getKey(),
            'entry_id' => $entry->getKey(),
            'participant_id' => $participant->getKey(),
            'status' => EligibilityStatus::Eligible,
        ]);
        $entry->update(['status' => EntryStatus::Locked->value]);
        $disciplineEntry = (new SaveDisciplineEntry)->handle(
            $admin,
            $event,
            $discipline,
            $entry,
            [['participant_id' => $participant->getKey(), 'is_starter' => true]],
            'locked',
        );

        return $disciplineEntry;
    }
}
