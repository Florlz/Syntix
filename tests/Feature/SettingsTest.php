<?php

namespace Tests\Feature;

use App\Enums\EventRole;
use App\Enums\EventState;
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

        $this->patch('/settings/profile', [])->assertRedirect('/login');
        $this->put('/settings/password', [])->assertRedirect('/login');
        $this->patch('/settings/preferences', [])->assertRedirect('/login');
        $this->delete('/settings/sessions', [])->assertRedirect('/login');

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
                ->missing('preferences.theme')
                ->missing('preference_options.themes')
                ->where('auth.user.preferences.text_size', 'large')
                ->missing('auth.user.preferences.theme')
                ->missing('auth.user.preferences.secret')
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

    public function test_settings_password_requires_confirmation_and_existing_password_rules(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($originalHash, $user->refresh()->password);

        $this->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-long-password',
                'password_confirmation' => 'new-long-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Hash::check('password', $user->refresh()->password));
        $this->assertTrue(Hash::check('new-long-password', $user->password));
    }

    public function test_profile_accepts_and_persists_a_trimmed_lowercase_version_of_the_authenticated_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Original Name',
                'email' => '  ORIGINAL@EXAMPLE.COM  ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    public function test_profile_rejects_a_case_insensitive_duplicate_before_database_persistence(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Owner',
                'email' => "  TAKEN@EXAMPLE.COM\t",
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('owner@example.com', $user->refresh()->email);
    }

    public function test_profile_rejects_duplicate_and_invalid_values_without_changing_the_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => '',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame('Original Name', $user->refresh()->name);
        $this->assertSame('original@example.com', $user->email);
    }

    public function test_profile_ignores_protected_fields_and_cannot_escalate_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'safe@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Safe Updated Name',
                'email' => 'safe-updated@example.com',
                'role' => 'global_admin',
                'is_admin' => true,
                'is_global_admin' => true,
                'account_state' => 'active',
                'password' => 'attacker-controlled-password',
                'user_id' => $other->getKey(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings');

        $this->assertSame('Safe Updated Name', $user->refresh()->name);
        $this->assertSame('safe-updated@example.com', $user->email);
        $this->assertFalse($user->fresh()->is_global_admin);
        $this->assertSame('other@example.com', $other->refresh()->email);
        $this->assertTrue(Hash::check('password', $user->password));
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
            'notifications' => [
                'approvals' => true,
                'security' => true,
            ],
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

    public function test_theme_is_not_part_of_the_supported_preference_contract(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'theme' => 'dark',
                'text_size' => 'large',
                'default_landing' => 'sports',
            ],
        ]);

        $this->assertArrayNotHasKey('theme', $user->normalizedPreferences());
        $this->assertSame('large', $user->normalizedPreferences()['text_size']);

        $this->actingAs($user)
            ->patch('/settings/preferences', ['theme' => 'dark'])
            ->assertSessionHasErrors('theme');

        $this->assertSame('dark', $user->refresh()->preferences['theme']);
    }

    public function test_notification_preferences_preserve_other_preferences_and_keep_security_alerts_on(): void
    {
        $user = User::factory()->create([
            'is_global_admin' => true,
            'preferences' => [
                'theme' => 'dark',
                'default_landing' => 'sports',
                'notifications' => [
                    'approvals' => true,
                    'security' => false,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'notifications' => [
                    'approvals' => false,
                    'security' => false,
                ],
            ])
            ->assertSessionHasNoErrors();

        $preferences = $user->refresh()->preferences;
        $this->assertArrayNotHasKey('theme', $preferences);
        $this->assertSame('sports', $preferences['default_landing']);
        $this->assertFalse($preferences['notifications']['approvals']);
        $this->assertTrue($preferences['notifications']['security']);
    }

    public function test_non_global_admin_cannot_update_global_admin_notification_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'notifications' => ['approvals' => false],
            ])
            ->assertForbidden();
    }

    public function test_inertia_does_not_share_a_theme_scope(): void
    {
        $admin = User::factory()->create(['is_global_admin' => true]);

        $this->actingAs($admin)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->missing('ui.theme_scope'));

        $this->actingAs($admin)
            ->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->missing('ui.theme_scope'));
    }

    public function test_archived_accessible_workspace_can_remain_the_read_only_default(): void
    {
        $user = User::factory()->create();
        $archived = Event::factory()->create([
            'state' => EventState::Archived,
            'archived_at' => now(),
        ]);
        EventUserRole::query()->create([
            'event_id' => $archived->getKey(),
            'user_id' => $user->getKey(),
            'role' => EventRole::Admin,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', ['default_event_id' => $archived->getKey()])
            ->assertSessionHasNoErrors();

        $this->assertSame($archived->getKey(), $user->refresh()->preferences['default_event_id']);
    }

    public function test_nonexistent_workspace_is_rejected_without_changing_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'default_event_id' => null,
                'default_landing' => 'overview',
            ],
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'default_event_id' => 999999,
                'default_landing' => 'sports',
            ])
            ->assertSessionHasErrors('default_event_id');

        $this->assertNull($user->refresh()->preferences['default_event_id']);
        $this->assertSame('overview', $user->preferences['default_landing']);
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

    public function test_partial_preference_update_repairs_a_stale_default_event_before_persisting(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'text_size' => 'default',
                'contrast' => 'default',
                'reduce_motion' => false,
                'default_event_id' => 999999,
                'default_landing' => 'sports',
            ],
        ]);
        $accessible = Event::factory()->create(['name' => 'Accessible event']);

        EventUserRole::query()->create([
            'event_id' => $accessible->getKey(),
            'user_id' => $user->getKey(),
            'role' => EventRole::Admin,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', ['text_size' => 'large'])
            ->assertSessionHasNoErrors();

        $preferences = $user->refresh()->preferences;

        $this->assertSame('large', $preferences['text_size']);
        $this->assertSame((string) $accessible->getKey(), $preferences['default_event_id']);
        $this->assertSame('sports', $preferences['default_landing']);
    }

    public function test_account_without_workspaces_can_still_open_settings_and_save_account_preferences(): void
    {
        $user = User::factory()->create(['preferences' => null]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.default_event_id', null)
                ->where('preferences.default_landing', 'overview')
                ->has('events', 0));

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'default_landing' => 'overview',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('overview', $user->refresh()->preferences['default_landing']);
    }

    public function test_deleted_saved_workspace_falls_back_without_redirecting_or_crashing(): void
    {
        $user = User::factory()->create();
        $deletedEventId = Event::factory()->create()->getKey();
        $user->update(['preferences' => ['default_event_id' => $deletedEventId]]);
        Event::query()->whereKey($deletedEventId)->delete();

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.default_event_id', null)
                ->has('events', 0));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('event', null));
    }

    public function test_preference_sections_can_save_independently_without_overwriting_each_other(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'text_size' => 'large',
                'contrast' => 'high',
                'reduce_motion' => true,
                'default_event_id' => null,
                'default_landing' => 'sports',
            ],
        ]);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'text_size' => 'x-large',
                'contrast' => 'default',
                'reduce_motion' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'text_size' => 'x-large',
            'contrast' => 'default',
            'reduce_motion' => false,
            'default_event_id' => null,
            'default_landing' => 'sports',
            'notifications' => [
                'approvals' => true,
                'security' => true,
            ],
        ], $user->refresh()->preferences);

        $this->actingAs($user)
            ->patch('/settings/preferences', [
                'default_event_id' => null,
                'default_landing' => 'departments',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'text_size' => 'x-large',
            'contrast' => 'default',
            'reduce_motion' => false,
            'default_event_id' => null,
            'default_landing' => 'departments',
            'notifications' => [
                'approvals' => true,
                'security' => true,
            ],
        ], $user->refresh()->preferences);
    }

    public function test_non_global_dashboard_shared_event_follows_saved_default_workspace(): void
    {
        $user = User::factory()->create();
        $defaultEvent = Event::factory()->create(['name' => 'Default event']);
        $otherEvent = Event::factory()->create(['name' => 'Other event']);

        EventUserRole::query()->insert([
            [
                'event_id' => $defaultEvent->getKey(),
                'user_id' => $user->getKey(),
                'role' => EventRole::Admin->value,
                'granted_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $otherEvent->getKey(),
                'user_id' => $user->getKey(),
                'role' => EventRole::Admin->value,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $user->update(['preferences' => ['default_event_id' => $defaultEvent->getKey()]]);

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.id', (string) $defaultEvent->getKey())
                ->where('auth.active_event.id', (string) $defaultEvent->getKey()));
    }

    public function test_legacy_boolean_preferences_are_normalized_without_truthiness_surprises(): void
    {
        $user = User::factory()->create([
            'preferences' => ['reduce_motion' => 'false'],
        ]);

        $this->actingAs($user)->get('/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.reduce_motion', false)
                ->where('auth.user.preferences.reduce_motion', false));
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

    public function test_settings_exposes_safe_session_metadata_without_raw_session_ids(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['settings_test' => true])->get('/settings');
        $currentSessionId = app('session.store')->getId();

        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edg/126.0.0.0',
                'payload' => 'current',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'other-session',
                'user_id' => $user->getKey(),
                'ip_address' => '10.0.0.8',
                'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/126.0.0.0 Mobile Safari/537.36',
                'payload' => 'other',
                'last_activity' => now()->subHours(2)->timestamp,
            ],
        ]);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->get('/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('sessions.0.is_current', true)
                ->where('sessions.0.browser', 'Microsoft Edge')
                ->where('sessions.1.device_type', 'Mobile')
                ->where('sessions.1.key', hash_hmac('sha256', 'other-session', config('app.key')))
                ->missing('sessions.0.id')
                ->missing('sessions.1.id'));
    }

    public function test_user_can_revoke_only_their_own_non_current_session_with_password_confirmation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->withSession(['settings_test' => true])->get('/settings');
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
                'last_activity' => now()->subHour()->timestamp,
            ],
            [
                'id' => 'different-user-session',
                'user_id' => $otherUser->getKey(),
                'payload' => 'other-user',
                'last_activity' => now()->subHour()->timestamp,
            ],
        ]);

        $targetKey = hash_hmac('sha256', 'other-session', config('app.key'));

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete("/settings/sessions/{$targetKey}", ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete("/settings/sessions/{$targetKey}", ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'different-user-session']);

        $currentKey = hash_hmac('sha256', $currentSessionId, config('app.key'));
        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete("/settings/sessions/{$currentKey}", ['current_password' => 'password'])
            ->assertSessionHasErrors('session');
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
    }

    public function test_signing_out_other_sessions_does_not_touch_other_users_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user)->withSession(['settings_test' => true])->get('/settings');
        $currentSessionId = app('session.store')->getId();

        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->getKey(),
                'payload' => 'current',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'user-other-session',
                'user_id' => $user->getKey(),
                'payload' => 'other',
                'last_activity' => now()->subHours(3)->timestamp,
            ],
            [
                'id' => 'different-user-session',
                'user_id' => $otherUser->getKey(),
                'payload' => 'different user',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete('/settings/sessions', ['current_password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId, 'user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('sessions', ['id' => 'user-other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'different-user-session', 'user_id' => $otherUser->getKey()]);

        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->delete('/settings/sessions', ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
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
