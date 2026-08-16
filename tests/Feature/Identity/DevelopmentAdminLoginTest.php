<?php

namespace Tests\Feature\Identity;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DevelopmentAdminSeeder;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_development_admin_can_log_in_to_the_global_dashboard(): void
    {
        $this->seed(SiklabReferenceSeeder::class);
        $this->seed(DevelopmentAdminSeeder::class);

        $this->post('/login', [
            'email' => 'admin@syntix.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $admin = User::query()->where('email', 'admin@syntix.test')->firstOrFail();
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->isGlobalAdmin());
        $this->assertSame(1, User::query()->where('is_global_admin', true)->count());
        $this->assertSame(42, Event::query()->where('slug', 'siklab-2026')->firstOrFail()
            ->competitions()->withCount('divisions')->get()->sum('divisions_count'));

        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('capabilities.global_admin', true)
            ->has('programme', 42));
    }

    public function test_successful_global_admin_login_creates_one_security_notification_without_page_view_duplicates(): void
    {
        $this->seed(SiklabReferenceSeeder::class);
        $this->seed(DevelopmentAdminSeeder::class);

        $this->post('/login', [
            'email' => 'admin@syntix.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $admin = User::query()->where('email', 'admin@syntix.test')->firstOrFail();
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('security_login', $admin->notifications()->firstOrFail()->data['kind']);

        $this->get('/dashboard')->assertOk();
        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }
}
