<?php

namespace Tests\Feature\Event;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Events\RevokeEventRole;
use App\Actions\Identity\BootstrapEventCreator;
use App\Enums\EventRole;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_creator_does_not_receive_an_event_role_automatically(): void
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);

        $this->assertDatabaseMissing('event_user_roles', [
            'event_id' => $event->getKey(),
            'user_id' => $creator->getKey(),
        ]);
        $this->assertFalse((new EventPolicy)->view($creator, $event));
    }

    public function test_only_the_creator_can_grant_the_first_admin_and_roles_are_event_scoped(): void
    {
        $creator = $this->bootstrapCreator();
        $eventA = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        $eventB = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2027']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        (new GrantEventRole)->handle($creator, $eventA, $admin, EventRole::Admin);
        (new GrantEventRole)->handle($creator, $eventB, $creator, EventRole::Admin);

        $this->assertTrue((new EventPolicy)->view($admin, $eventA));
        $this->assertFalse((new EventPolicy)->view($admin, $eventB));
        $this->assertFalse((new EventPolicy)->create($admin));

        $this->expectException(AuthorizationException::class);
        (new GrantEventRole)->handle($admin, $eventB, $admin, EventRole::Judge);
    }

    public function test_revoking_a_role_immediately_removes_event_access(): void
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $membership = (new GrantEventRole)->handle($creator, $event, $admin, EventRole::Admin);

        $this->assertTrue((new EventPolicy)->view($admin, $event));

        (new RevokeEventRole)->handle($membership, $admin, 'Role no longer required');

        $this->assertFalse((new EventPolicy)->view($admin->fresh(), $event));
    }

    private function bootstrapCreator(): User
    {
        return (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'a-secure-bootstrap-password',
        ]);
    }
}
