<?php

namespace Tests\Feature\Public;

use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicScoreboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_scoreboard_is_anonymous_and_sanitized(): void
    {
        $event = Event::factory()->create(['slug' => 'siklab-public']);
        $unit = OrganizationalUnit::factory()->create();
        EventDelegation::factory()->create([
            'event_id' => $event->getKey(),
            'organizational_unit_id' => $unit->getKey(),
        ]);

        $response = $this->get('/events/siklab-public/scoreboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Scoreboard')
            ->where('event.name', $event->name)
            ->has('competitions')
            ->has('leaderboard')
            ->where('auth.user', null)
            ->missing('leaderboard.0.student_number'));
        $this->assertGuest();
    }
}
