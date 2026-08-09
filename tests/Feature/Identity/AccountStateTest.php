<?php

namespace Tests\Feature\Identity;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapEventCreator;
use App\Actions\Identity\DisableUser;
use App\Actions\Identity\EnableUser;
use App\Actions\Identity\GrantPlatformCapability;
use App\Actions\Identity\RevokePlatformCapability;
use App\Enums\AccountState;
use App\Enums\EventRole;
use App\Enums\PlatformCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_is_one_time_and_grants_only_event_creator(): void
    {
        $user = (new BootstrapEventCreator)->handle([
            'name' => 'Platform Creator',
            'email' => 'creator@example.com',
            'password' => 'a-secure-bootstrap-password',
        ], 'test deployment');

        $this->assertTrue($user->isActive());
        $this->assertTrue($user->hasActivePlatformCapability(PlatformCapability::EventCreator));
        $this->assertCount(0, $user->eventRoles);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event_creator.bootstrapped',
            'actor_id' => null,
            'target_id' => (string) $user->getKey(),
        ]);

        $this->expectException(\DomainException::class);

        (new BootstrapEventCreator)->handle([
            'name' => 'Second Creator',
            'email' => 'second@example.com',
            'password' => 'another-secure-password',
        ]);
    }

    public function test_disabling_an_account_revokes_sessions_and_blocks_login(): void
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        (new GrantEventRole)->handle($creator, $event, $creator, EventRole::Admin);

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

        $disabled = (new DisableUser)->handle($creator, $target, 'No longer assigned to SIKLAB operations', $event);

        $this->assertSame(AccountState::Disabled, $disabled->accountState());
        $this->assertDatabaseMissing('sessions', ['id' => 'disabled-session']);
        $this->assertNull($disabled->fresh()->remember_token);

        $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();

        $enabled = (new EnableUser)->handle($creator, $disabled->fresh(), $event);

        $this->assertSame(AccountState::Active, $enabled->accountState());
    }

    public function test_last_active_event_creator_cannot_be_revoked_or_disabled(): void
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        (new GrantEventRole)->handle($creator, $event, $creator, EventRole::Admin);

        $creatorGrant = $creator->platformCapabilities()->active()->first();

        $this->expectException(\DomainException::class);
        (new RevokePlatformCapability)->handle($creator, $creatorGrant, 'Replacing the platform creator');
    }

    public function test_last_creator_can_be_replaced_before_the_original_is_revoked(): void
    {
        $creator = $this->bootstrapCreator();
        $event = (new CreateEvent)->handle($creator, ['name' => 'SIKLAB 2026']);
        (new GrantEventRole)->handle($creator, $event, $creator, EventRole::Admin);
        $replacement = User::factory()->create(['email' => 'replacement@example.com']);

        (new GrantPlatformCapability)->handle($creator, $replacement, PlatformCapability::EventCreator);
        $creatorGrant = $creator->fresh()->platformCapabilities()->active()->firstOrFail();

        (new RevokePlatformCapability)->handle($creator, $creatorGrant, 'Replacing the platform creator');

        $this->assertFalse($creator->fresh()->hasActivePlatformCapability(PlatformCapability::EventCreator));
        $this->assertTrue($replacement->fresh()->hasActivePlatformCapability(PlatformCapability::EventCreator));
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
