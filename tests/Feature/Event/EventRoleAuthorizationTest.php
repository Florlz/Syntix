<?php

namespace Tests\Feature\Event;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Events\RevokeEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\EventRole;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_does_not_receive_an_event_role_automatically(): void
    {
        $admin = $this->bootstrapAdmin();
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2026']);

        $this->assertDatabaseMissing('event_user_roles', [
            'event_id' => $event->getKey(),
            'user_id' => $admin->getKey(),
        ]);
        $this->assertTrue((new EventPolicy)->view($admin, $event));
    }

    public function test_global_admin_grants_only_judge_or_tabulator_roles(): void
    {
        $admin = $this->bootstrapAdmin();
        $eventA = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2026']);
        $eventB = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2027']);
        $judge = User::factory()->create(['email' => 'judge@example.com']);

        (new GrantEventRole)->handle($admin, $eventA, $judge, EventRole::Judge);

        $this->assertTrue((new EventPolicy)->view($judge, $eventA));
        $this->assertFalse((new EventPolicy)->view($judge, $eventB));
        $this->assertFalse((new EventPolicy)->create($judge));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Event Admin is retired');
        (new GrantEventRole)->handle($admin, $eventB, $judge, EventRole::Admin);
    }

    public function test_global_admin_can_manage_every_event_without_event_memberships(): void
    {
        $globalAdmin = User::factory()->create([
            'email' => 'global-admin@example.com',
            'is_global_admin' => true,
        ]);
        $eventA = (new CreateEvent)->handle($globalAdmin, ['name' => 'SIKLAB 2026']);
        $eventB = (new CreateEvent)->handle($globalAdmin, ['name' => 'SIKLAB 2027']);

        $this->assertDatabaseMissing('event_user_roles', [
            'user_id' => $globalAdmin->getKey(),
        ]);
        $this->assertTrue($globalAdmin->fresh()->hasAdminAccess($eventA));
        $this->assertTrue($globalAdmin->fresh()->hasAdminAccess($eventB));
        $this->assertTrue((new EventPolicy)->view($globalAdmin, $eventA));
        $this->assertTrue((new EventPolicy)->view($globalAdmin, $eventB));
        $this->assertTrue((new EventPolicy)->create($globalAdmin));
    }

    public function test_revoking_a_role_immediately_removes_event_access(): void
    {
        $admin = $this->bootstrapAdmin();
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2026']);
        $judge = User::factory()->create(['email' => 'judge@example.com']);
        $membership = (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);

        $this->assertTrue((new EventPolicy)->view($judge, $event));

        (new RevokeEventRole)->handle($membership, $admin, 'Role no longer required');

        $this->assertFalse((new EventPolicy)->view($judge->fresh(), $event));
    }

    public function test_archived_event_roles_cannot_be_revoked(): void
    {
        $admin = $this->bootstrapAdmin();
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2026']);
        $judge = User::factory()->create(['email' => 'archived-judge@example.com']);
        $membership = (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);
        $event->update(['state' => 'archived']);

        try {
            (new RevokeEventRole)->handle($membership, $admin, 'Role no longer required');
            $this->fail('Archived event roles must be read-only.');
        } catch (AuthorizationException) {
            // Expected: archive guard blocks the mutation before save.
        }

        $this->assertDatabaseHas('event_user_roles', [
            'id' => $membership->getKey(),
            'revoked_at' => null,
        ]);
    }

    private function bootstrapAdmin(): User
    {
        return (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'a-secure-bootstrap-password',
        ]);
    }
}
