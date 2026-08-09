<?php

namespace Tests\Feature\Identity;

use App\Actions\Events\CreateEvent;
use App\Actions\Identity\BootstrapEventCreator;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Identity\DisableUser;
use App\Actions\Identity\EnableUser;
use App\Actions\Identity\GrantPlatformCapability;
use App\Actions\Identity\RevokePlatformCapability;
use App\Enums\AccountState;
use App\Enums\PlatformCapability;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_bootstrap_alias_creates_the_single_global_admin_without_legacy_capabilities(): void
    {
        $user = (new BootstrapEventCreator)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'a-secure-bootstrap-password',
        ], 'test deployment');

        $this->assertTrue($user->isGlobalAdmin());
        $this->assertFalse($user->hasActivePlatformCapability(PlatformCapability::EventCreator));
        $this->assertCount(0, $user->eventRoles);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'global_admin.bootstrapped',
            'actor_id' => null,
            'target_id' => (string) $user->getKey(),
        ]);

        $this->expectException(\DomainException::class);
        (new BootstrapGlobalAdmin)->handle([
            'name' => 'Second Global Admin',
            'email' => 'second@example.com',
            'password' => 'another-secure-password',
        ]);
    }

    public function test_global_admin_can_disable_a_worker_revoke_sessions_and_restore_the_account(): void
    {
        $admin = $this->bootstrapAdmin();
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB 2026']);
        $target = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'password',
        ]);

        DB::table('sessions')->insert([
            'id' => 'disabled-session',
            'user_id' => $target->getKey(),
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $disabled = (new DisableUser)->handle($admin, $target, 'No longer assigned to SIKLAB operations', $event);

        $this->assertSame(AccountState::Disabled, $disabled->accountState());
        $this->assertDatabaseMissing('sessions', ['id' => 'disabled-session']);
        $this->assertNull($disabled->fresh()->remember_token);

        $this->post('/login', ['email' => 'disabled@example.com', 'password' => 'password']);
        $this->assertGuest();

        $enabled = (new EnableUser)->handle($admin, $disabled->fresh(), $event);
        $this->assertSame(AccountState::Active, $enabled->accountState());
    }

    public function test_the_global_admin_cannot_be_disabled(): void
    {
        $admin = $this->bootstrapAdmin();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sole Global Admin');

        (new DisableUser)->handle($admin, $admin, 'Invalid replacement attempt');
    }

    public function test_legacy_event_creator_grants_authorize_nothing_and_can_be_revoked_by_global_admin(): void
    {
        $admin = $this->bootstrapAdmin();
        $legacyUser = User::factory()->create();
        $grant = PlatformCapabilityGrant::create([
            'user_id' => $legacyUser->getKey(),
            'capability' => PlatformCapability::EventCreator,
            'granted_by' => $admin->getKey(),
            'granted_at' => now(),
            'reason' => 'Legacy fixture',
        ]);

        $this->assertTrue($legacyUser->hasActivePlatformCapability(PlatformCapability::EventCreator));
        $this->assertFalse($legacyUser->hasAnyAdminAccess());

        try {
            (new CreateEvent)->handle($legacyUser, ['name' => 'Unauthorized Event']);
            $this->fail('The legacy capability unexpectedly authorized event creation.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('events', ['name' => 'Unauthorized Event']);
        }

        (new RevokePlatformCapability)->handle($admin, $grant, 'Retiring legacy access');
        $this->assertFalse($legacyUser->fresh()->hasActivePlatformCapability(PlatformCapability::EventCreator));

        $this->expectException(\DomainException::class);
        (new GrantPlatformCapability)->handle($admin, $legacyUser, PlatformCapability::EventCreator);
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
