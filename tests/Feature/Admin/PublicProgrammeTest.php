<?php

namespace Tests\Feature\Admin;

use App\Enums\EventState;
use App\Enums\PublicationState;
use App\Models\Competition;
use App\Models\CompetitionCoverImage;
use App\Models\Division;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicProgrammeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_global_admin_can_open_the_public_programme_desk(): void
    {
        $event = Event::factory()->create(['state' => EventState::Live]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('admin.public-programme.index', $event))
            ->assertForbidden();

        $admin = $this->globalAdmin();

        $this->actingAs($admin)
            ->get(route('admin.public-programme.index', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/PublicProgramme')
                ->where('event.id', (string) $event->getKey()));
    }

    public function test_global_admin_can_open_any_event_public_programme_without_an_event_role(): void
    {
        $event = Event::factory()->create(['state' => EventState::Live]);
        $globalAdmin = User::factory()->create(['is_global_admin' => true]);

        $this->actingAs($globalAdmin)
            ->get(route('admin.public-programme.index', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/PublicProgramme')
                ->where('event.id', (string) $event->getKey()));
    }

    public function test_schedule_changes_stay_private_until_explicit_republication_and_can_be_withdrawn(): void
    {
        $event = Event::factory()->create([
            'name' => 'SIKLAB Programme',
            'slug' => 'siklab-programme',
            'state' => EventState::Live,
            'starts_at' => now(),
        ]);
        $admin = $this->globalAdmin();
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Basketball']);
        $division = Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => 'Men']);

        $this->actingAs($admin)->post(route('admin.venues.store', $event), [
            'name' => 'CSPC Gymnasium',
            'code' => 'GYM',
            'location' => 'Main Campus',
            'description' => 'Enter through the east gate.',
            'is_active' => true,
        ])->assertRedirect();
        $venue = Venue::query()->sole();

        $this->actingAs($admin)->post(route('admin.schedules.store', $event), [
            'competition_division_id' => $division->getKey(),
            'venue_id' => $venue->getKey(),
            'title' => 'Basketball Eliminations',
            'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'ends_at' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
            'status' => 'scheduled',
            'notes' => 'Private call time: 8:00 AM',
        ])->assertRedirect();
        $schedule = Schedule::query()->sole();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('competitions.0.schedules', 0));

        $this->actingAs($admin)
            ->post(route('admin.schedules.publish', [$event, $schedule]))
            ->assertRedirect();

        $firstPublication = SchedulePublication::query()->sole();
        $this->assertSame(1, $firstPublication->revision);
        $this->assertSame(PublicationState::Published, $firstPublication->state);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('competitions.0.schedules.0.title', 'Basketball Eliminations')
            ->where('competitions.0.schedules.0.venue.name', 'CSPC Gymnasium')
            ->where('competitions.0.schedules.0.venue.location', 'Main Campus')
            ->missing('competitions.0.schedules.0.notes')
            ->missing('competitions.0.schedules.0.published_by'));

        $this->actingAs($admin)->patch(route('admin.schedules.update', [$event, $schedule]), [
            'competition_division_id' => $division->getKey(),
            'venue_id' => $venue->getKey(),
            'title' => 'Basketball Semifinals',
            'starts_at' => now()->addDays(2)->setTime(13, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(2)->setTime(15, 0)->toDateTimeString(),
            'status' => 'scheduled',
            'notes' => 'Updated internal call time.',
        ])->assertRedirect();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('competitions.0.schedules.0.title', 'Basketball Eliminations'));

        $this->actingAs($admin)
            ->post(route('admin.schedules.publish', [$event, $schedule]))
            ->assertRedirect();

        $this->assertDatabaseHas('schedule_publications', [
            'id' => $firstPublication->getKey(),
            'state' => PublicationState::Superseded->value,
        ]);
        $secondPublication = SchedulePublication::query()->where('revision', 2)->sole();
        $this->assertSame(PublicationState::Published, $secondPublication->state);
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('competitions.0.schedules.0.title', 'Basketball Semifinals'));

        $this->actingAs($admin)->post(route('admin.schedule-publications.withdraw', [$event, $secondPublication]), [
            'reason' => 'Venue programme is being corrected.',
        ])->assertRedirect();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('competitions.0.schedules', 0));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'schedule_publication.withdrawn',
            'reason' => 'Venue programme is being corrected.',
        ]);
    }

    public function test_competition_cover_stays_private_until_publish_and_public_payload_never_exposes_storage_metadata(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $event = Event::factory()->create([
            'slug' => 'cover-programme',
            'state' => EventState::Live,
            'starts_at' => now(),
        ]);
        $admin = $this->globalAdmin();
        $competition = Competition::factory()->create(['event_id' => $event->getKey(), 'name' => 'Volleyball']);
        Division::factory()->create(['competition_id' => $competition->getKey(), 'name' => 'Women']);

        $this->actingAs($admin)->post(route('admin.cover-images.store', [$event, $competition]), [
            'cover' => $this->landscapePng(),
            'alt_text' => 'Volleyball court inside the CSPC gymnasium.',
        ])->assertRedirect();

        $cover = CompetitionCoverImage::query()->sole();
        $this->assertSame(PublicationState::Draft, $cover->state);
        Storage::disk('local')->assertExists($cover->private_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $preview = $this->actingAs($admin)
            ->get(route('admin.cover-images.preview', [$event, $cover]))
            ->assertOk();
        $this->assertStringContainsString('private', (string) $preview->headers->get('Cache-Control'));

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('competitions.0.cover', null));

        $this->actingAs($admin)
            ->post(route('admin.cover-images.publish', [$event, $cover]))
            ->assertRedirect();

        $cover->refresh();
        $this->assertSame(PublicationState::Published, $cover->state);
        Storage::disk('public')->assertExists($cover->public_path);

        $response = $this->get('/');
        $response->assertInertia(fn ($page) => $page
            ->where('competitions.0.cover.alt', 'Volleyball court inside the CSPC gymnasium.')
            ->where('competitions.0.cover.width', 1200)
            ->where('competitions.0.cover.height', 675)
            ->where('competitions.0.cover.url', fn ($value) => is_string($value) && str_contains($value, '/storage/events/'))
            ->missing('competitions.0.cover.private_path')
            ->missing('competitions.0.cover.public_path')
            ->missing('competitions.0.cover.created_by'));

        $page = json_encode($response->viewData('page'));
        $this->assertIsString($page);
        $this->assertStringNotContainsString($cover->private_path, $page);
    }

    public function test_records_from_another_event_cannot_be_modified_through_the_programme_routes(): void
    {
        $event = Event::factory()->create(['state' => EventState::Live]);
        $otherEvent = Event::factory()->create(['state' => EventState::Live]);
        $admin = $this->globalAdmin();
        $otherVenue = Venue::create([
            'event_id' => $otherEvent->getKey(),
            'name' => 'Other Campus Court',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.venues.update', [$event, $otherVenue]), [
            'name' => 'Tampered Venue',
            'code' => null,
            'location' => null,
            'description' => null,
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseHas('venues', [
            'id' => $otherVenue->getKey(),
            'name' => 'Other Campus Court',
        ]);
    }

    public function test_archived_event_programmes_are_visible_to_admins_but_read_only(): void
    {
        $event = Event::factory()->create([
            'state' => EventState::Archived,
            'archived_at' => now(),
        ]);
        $admin = $this->globalAdmin();

        $this->actingAs($admin)
            ->get(route('admin.public-programme.index', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('event.archived', true));

        $this->actingAs($admin)->post(route('admin.venues.store', $event), [
            'name' => 'Archived Venue',
            'code' => null,
            'location' => null,
            'description' => null,
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('venues', ['event_id' => $event->getKey()]);
    }

    private function globalAdmin(): User
    {
        return User::factory()->create(['is_global_admin' => true]);
    }

    private function landscapePng(): UploadedFile
    {
        $width = 1200;
        $height = 675;
        $row = "\0".str_repeat("\x0B\x2E\x4F", $width);
        $pixels = str_repeat($row, $height);
        $png = "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$this->pngChunk('IDAT', gzcompress($pixels, 9))
            .$this->pngChunk('IEND', '');

        return UploadedFile::fake()->createWithContent('volleyball.png', $png);
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
