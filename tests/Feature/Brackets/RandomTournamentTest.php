<?php

namespace Tests\Feature\Brackets;

use App\Actions\Brackets\GenerateRandomTournament;
use App\Actions\Brackets\PublishBracket;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\Competition;
use App\Models\ContestEntry;
use App\Models\Event;
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

        $tournament = (new GenerateRandomTournament)->handle($admin, $division, (string) Str::uuid());
        $bracket = $tournament->bracketVersions()->firstOrFail();

        $this->assertSame(15, $bracket->nodes()->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'bye')->count());
        $this->assertSame(1, $bracket->nodes()->where('node_type', 'reset_final')->count());
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
}
