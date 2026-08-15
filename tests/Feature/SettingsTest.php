<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_authentication_and_exposes_safe_account_data(): void
    {
        $this->get('/settings')->assertRedirect('/login');

        $user = User::factory()->create([
            'name' => 'Student Leader',
            'email' => 'leader@example.com',
            'preferences' => ['text_size' => 'large', 'reduce_motion' => true],
        ]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('profile.name', 'Student Leader')
                ->where('profile.email', 'leader@example.com')
                ->where('preferences.text_size', 'large')
                ->where('preferences.contrast', 'default')
                ->where('preferences.reduce_motion', true)
                ->where('auth.user.preferences.text_size', 'large')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }

    public function test_profile_and_password_settings_routes_keep_existing_behavior(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Updated Leader',
                'email' => 'updated@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings');

        $this->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings');

        $user->refresh();
        $this->assertSame('Updated Leader', $user->name);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_preferences_validate_and_persist_only_events_the_user_can_access(): void
    {
        $user = User::factory()->create();
        $accessible = Event::factory()->create(['name' => 'Accessible event']);
        $inaccessible = Event::factory()->create(['name' => 'Private event']);
        EventUserRole::query()->create([
            'event_id' => $accessible->getKey(),
            'user_id' => $user->getKey(),
            'role' => EventRole::Admin,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'text_size' => 'x-large',
                'contrast' => 'high',
                'reduce_motion' => true,
                'default_event_id' => $accessible->getKey(),
                'default_landing' => 'sports',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'text_size' => 'x-large',
            'contrast' => 'high',
            'reduce_motion' => true,
            'default_event_id' => $accessible->getKey(),
            'default_landing' => 'sports',
        ], $user->refresh()->preferences);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'text_size' => 'tiny',
                'contrast' => 'default',
                'reduce_motion' => false,
                'default_event_id' => $inaccessible->getKey(),
                'default_landing' => 'overview',
            ])
            ->assertSessionHasErrors(['text_size', 'default_event_id']);
    }

    public function test_inaccessible_saved_default_event_falls_back_to_first_accessible_event(): void
    {
        $user = User::factory()->create();
        $accessible = Event::factory()->create(['created_at' => now()->subDay()]);
        $inaccessible = Event::factory()->create(['created_at' => now()]);
        EventUserRole::query()->create([
            'event_id' => $accessible->getKey(),
            'user_id' => $user->getKey(),
            'role' => EventRole::Admin,
            'granted_at' => now(),
        ]);
        $user->update(['preferences' => ['default_event_id' => $inaccessible->getKey()]]);

        $this->actingAs($user)->get('/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.default_event_id', (string) $accessible->getKey())
                ->where('auth.user.preferences.default_event_id', (string) $accessible->getKey())
                ->has('events', 1));
    }

    public function test_global_dashboard_uses_saved_first_page_on_initial_visit(): void
    {
        $admin = User::factory()->create([
            'is_global_admin' => true,
            'preferences' => ['default_landing' => 'sports'],
        ]);
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.sports.index', $event, absolute: false));
    }

    public function test_signing_out_other_sessions_requires_current_password_and_keeps_current_session(): void
    {
        $user = User::factory()->create();

        // Seed a session value so the database driver persists the current
        // session row and the request can prove it survives revocation.
        $response = $this->actingAs($user)
            ->withSession(['settings_test' => true])
            ->get('/settings');
        $currentSessionId = app('session.store')->getId();
        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->getKey(),
                'payload' => 'current',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'other-session',
                'user_id' => $user->getKey(),
                'payload' => 'other',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete('/settings/sessions', ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete('/settings/sessions', ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_disabled_accounts_cannot_save_settings(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'text_size' => 'large',
                'contrast' => 'high',
                'reduce_motion' => true,
                'default_event_id' => null,
                'default_landing' => 'overview',
            ])
            ->assertForbidden();
    }
}
