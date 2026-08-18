<?php

namespace Tests\Feature\Backend;

use App\Models\Competition;
use App\Models\Contest;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Schedule;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_default_seed_keeps_configuration_without_showcase_operations(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@syntix.test',
            'is_global_admin' => true,
        ]);
        $this->assertDatabaseHas('events', ['slug' => 'siklab-2026']);
        $this->assertGreaterThan(0, Competition::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Event::query()->count());
        $this->assertSame(0, Participant::query()->count());
        $this->assertSame(0, Entry::query()->count());
        $this->assertSame(0, Schedule::query()->count());
        $this->assertSame(0, Venue::query()->count());
        $this->assertSame(0, Contest::query()->count());
        $this->assertSame(0, ScoringAssignment::query()->count());
        $this->assertSame(0, UserInvitation::query()->count());
    }
}
