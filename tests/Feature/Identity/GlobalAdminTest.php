<?php

namespace Tests\Feature\Identity;

use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Actions\Identity\DisableUser;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_is_idempotent_for_the_same_global_admin(): void
    {
        $bootstrap = new BootstrapGlobalAdmin;
        $first = $bootstrap->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);
        $second = $bootstrap->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);

        $this->assertTrue($first->isGlobalAdmin());
        $this->assertTrue($first->is($second));
        $this->assertSame(1, User::query()->where('is_global_admin', true)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'global_admin.bootstrapped',
            'target_id' => (string) $first->getKey(),
        ]);
    }

    public function test_a_second_global_admin_is_rejected_by_the_database(): void
    {
        User::factory()->create(['is_global_admin' => true]);

        $this->expectException(QueryException::class);

        User::factory()->create(['is_global_admin' => true]);
    }

    public function test_the_sole_global_admin_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['is_global_admin' => true]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sole Global Admin');

        (new DisableUser)->handle($admin, $admin, 'No longer needed', $event);
    }
}
