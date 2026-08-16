<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_activity_notification_persists_the_shared_payload_contract(): void
    {
        $admin = User::factory()->create(['is_global_admin' => true]);
        $payload = [
            'kind' => 'approval_result',
            'title' => 'Result ready for review',
            'message' => 'Basketball Men · Semifinal 1 was submitted.',
            'event_id' => '42',
            'action' => [
                'label' => 'Review result',
                'route' => 'admin.approvals.index',
                'params' => ['event' => '42'],
            ],
        ];

        $admin->notify(new AdminActivityNotification($payload));

        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame($payload, $notification->data);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notifiable_id' => $admin->getKey(),
        ]);
    }

    public function test_admin_activity_notification_persists_immediately_when_the_queue_driver_is_database(): void
    {
        config()->set('queue.default', 'database');
        $admin = User::factory()->create(['is_global_admin' => true]);

        $admin->notify(new AdminActivityNotification([
            'kind' => 'security_login',
            'title' => 'New administrator sign-in',
            'message' => 'Chrome Â· macOS',
        ]));

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_user_can_mark_only_their_own_notifications_as_read(): void
    {
        $owner = User::factory()->create(['is_global_admin' => true]);
        $other = User::factory()->create();
        $payload = [
            'kind' => 'security_login',
            'title' => 'New administrator sign-in',
            'message' => 'A new administrator session was created.',
        ];

        $owner->notify(new AdminActivityNotification($payload));
        $other->notify(new AdminActivityNotification($payload));
        $ownerNotification = $owner->notifications()->firstOrFail();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->post("/notifications/{$ownerNotification->id}/read")
            ->assertSessionHasNoErrors();
        $this->assertNotNull($ownerNotification->fresh()->read_at);

        $this->actingAs($owner)
            ->post("/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_mark_all_read_only_updates_the_authenticated_users_notifications(): void
    {
        $owner = User::factory()->create(['is_global_admin' => true]);
        $other = User::factory()->create();
        $payload = [
            'kind' => 'security_login',
            'title' => 'New administrator sign-in',
            'message' => 'A new administrator session was created.',
        ];

        $owner->notify(new AdminActivityNotification($payload));
        $other->notify(new AdminActivityNotification($payload));
        $ownerNotification = $owner->notifications()->firstOrFail();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->post('/notifications/read-all')
            ->assertSessionHasNoErrors();

        $this->assertNotNull($ownerNotification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_global_admin_shared_props_include_a_small_unread_notification_projection(): void
    {
        $admin = User::factory()->create(['is_global_admin' => true]);
        $admin->notify(new AdminActivityNotification([
            'kind' => 'security_login',
            'title' => 'New administrator sign-in',
            'message' => 'A new administrator session was created.',
        ]));

        $this->actingAs($admin)
            ->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread_count', 1)
                ->where('notifications.recent.0.title', 'New administrator sign-in')
                ->where('notifications.recent.0.kind', 'security_login'));
    }
}
