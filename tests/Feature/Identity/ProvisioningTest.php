<?php

namespace Tests\Feature\Identity;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Identity\ProvisionUser;
use App\Enums\EventRole;
use App\Models\Competition;
use App\Models\Division;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioned_user_sets_up_once_through_a_hashed_invitation(): void
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);

        $result = (new ProvisionUser)->handle($admin, $event, [
            'name' => 'Judge One',
            'email' => 'judge@example.com',
        ]);

        $this->assertFalse(Hash::check('password', $result['user']->password));
        $this->assertNotSame($result['token'], $result['invitation']->token_hash);
        $this->get('/account-setup/'.$result['token'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('valid', true)
                ->where('conflict', false)
                ->where('email', 'judge@example.com'));

        $this->actingAs($admin)
            ->get('/account-setup/'.$result['token'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('valid', true)
                ->where('conflict', true)
                ->where('authenticatedEmail', 'creator@example.com'));
        $this->post(route('account.setup.switch', ['token' => $result['token']]))
            ->assertRedirect(route('account.setup', ['token' => $result['token']]));
        $this->assertGuest();

        $this->post('/account-setup/'.$result['token'], [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($result['user']->fresh());
        $this->assertTrue(Hash::check('new-secure-password', $result['user']->fresh()->password));
        $this->assertNotNull(UserInvitation::query()->find($result['invitation']->getKey())->consumed_at);
        $this->get('/account-setup/'.$result['token'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('valid', false));
    }

    public function test_admin_provisioning_response_contains_the_one_time_setup_url(): void
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);
        Competition::factory()->for($event)->has(Division::factory())->create();

        $this->actingAs($admin)
            ->post('/admin/events/'.$event->getKey().'/accounts', [
                'name' => 'Judge One',
                'email' => 'judge-'.uniqid().'@example.com',
                'role' => EventRole::Judge->value,
            ])
            ->assertRedirect();

        $setupUrl = session('setup_url');
        $this->assertIsString($setupUrl);
        $this->assertStringStartsWith(url('/account-setup/'), $setupUrl);
        $judge = User::query()->where('email', 'like', 'judge-%')->firstOrFail();
        $this->assertTrue($judge->hasActiveEventRole($event, EventRole::Judge));
        $this->assertDatabaseMissing('scoring_assignments', [
            'event_id' => $event->getKey(),
            'user_id' => $judge->getKey(),
        ]);
        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('flash.setup_url', $setupUrl));
    }

    public function test_only_an_event_admin_can_open_the_provisioning_screen(): void
    {
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'creator-'.uniqid().'@example.com',
            'password' => 'secure-bootstrap-password',
        ]);
        $event = (new CreateEvent)->handle($admin, ['name' => 'SIKLAB '.uniqid()]);
        $judge = User::factory()->create(['email' => 'judge-'.uniqid().'@example.com']);
        (new GrantEventRole)->handle($admin, $event, $judge, EventRole::Judge);

        $this->actingAs($judge)
            ->get('/admin/events/'.$event->getKey().'/accounts/create')
            ->assertForbidden();
    }
}
