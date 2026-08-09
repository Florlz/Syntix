<?php

namespace Tests\Feature\Event;

use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_creates_an_event_without_an_event_admin_membership(): void
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-bootstrap-password',
        ]);

        $response = $this->actingAs($admin)->post('/admin/events', [
            'name' => 'SIKLAB 2026',
            'slug' => 'siklab-2026',
        ]);

        $response->assertRedirect('/dashboard?event=1');
        $event = Event::query()->where('slug', 'siklab-2026')->firstOrFail();
        $this->assertSame($admin->getKey(), $event->created_by);
        $this->assertDatabaseCount('event_user_roles', 0);
    }

    public function test_a_non_global_user_cannot_create_an_event(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/admin/events', [
            'name' => 'Not allowed',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('events', ['name' => 'Not allowed']);
    }
}
