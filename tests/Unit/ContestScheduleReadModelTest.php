<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Event;
use App\Models\Venue;
use App\Services\ContestScheduleReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Schedule;
use Tests\TestCase;

class ContestScheduleReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_contest_schedule_wins_over_division_schedule_and_exposes_venue_identity(): void
    {
        [$event, $division, $contest] = $this->contestContext();
        $venue = Venue::create(['event_id' => $event->getKey(), 'name' => 'CSPC Auditorium', 'location' => 'Main campus']);

        Schedule::create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $division->getKey(),
            'title' => 'Division call',
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        Schedule::create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $division->getKey(),
            'contest_id' => $contest->getKey(),
            'venue_id' => $venue->getKey(),
            'title' => 'Contest call',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => 'scheduled',
        ]);

        $schedule = (new ContestScheduleReadModel)->for($contest);

        $this->assertSame('Contest call', $schedule['title']);
        $this->assertSame((string) $venue->getKey(), $schedule['venue']['id']);
        $this->assertSame('CSPC Auditorium', $schedule['venue']['name']);
        $this->assertSame('Main campus', $schedule['venue']['location']);
    }

    public function test_division_schedule_is_used_when_contest_has_no_schedule(): void
    {
        [$event, $division, $contest] = $this->contestContext();
        Schedule::create([
            'event_id' => $event->getKey(),
            'competition_division_id' => $division->getKey(),
            'title' => 'Division call',
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $schedule = (new ContestScheduleReadModel)->for($contest);

        $this->assertSame('Division call', $schedule['title']);
        $this->assertNull($schedule['venue']);
    }

    public function test_missing_schedule_is_explicitly_empty(): void
    {
        [, , $contest] = $this->contestContext();

        $this->assertSame([
            'starts_at' => null,
            'ends_at' => null,
            'title' => null,
            'venue' => null,
        ], (new ContestScheduleReadModel)->for($contest));
    }

    /** @return array{0: Event, 1: Division, 2: Contest} */
    private function contestContext(): array
    {
        $event = Event::factory()->create();
        $competition = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $competition->getKey()]);
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);

        return [$event, $division, $contest];
    }
}
