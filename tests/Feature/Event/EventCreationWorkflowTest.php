<?php

namespace Tests\Feature\Event;

use App\Actions\Identity\BootstrapEventCreator;
use App\Enums\EventRole;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_creator_creates_a_shell_and_separately_grants_first_admin(): void
    {
        $creator = (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->actingAs($creator)->post('/admin/events', [
            'name' => 'SIKLAB 2026',
            'slug' => 'siklab-2026',
            'first_admin_id' => $admin->getKey(),
        ]);

        $response->assertRedirect('/dashboard');
        $event = Event::query()->where('slug', 'siklab-2026')->firstOrFail();
        $this->assertDatabaseHas('event_user_roles', [
            'event_id' => $event->getKey(),
            'user_id' => $admin->getKey(),
            'role' => EventRole::Admin->value,
            'revoked_at' => null,
        ]);
        $this->assertFalse($creator->fresh()->hasActiveEventRole($event, EventRole::Admin));
        $this->assertSame(1, EventUserRole::query()->where('event_id', $event->getKey())->count());
    }

    public function test_an_event_admin_without_platform_capability_cannot_create_an_event(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/admin/events', [
            'name' => 'Not allowed',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('events', ['name' => 'Not allowed']);
    }
}
