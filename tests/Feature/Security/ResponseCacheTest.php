<?php

namespace Tests\Feature\Security;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponseCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_inertia_response_is_private_and_not_stored(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_public_scoreboard_does_not_receive_authenticated_private_headers(): void
    {
        $event = Event::factory()->create(['slug' => 'public-cache-test']);

        $response = $this->withSession(['setup_url' => 'https://private.example/setup'])
            ->get('/events/public-cache-test/scoreboard');

        $response->assertOk();
        $this->assertNotSame('private, no-store', $response->headers->get('Cache-Control'));
        $response->assertInertia(fn ($page) => $page->where('flash.setup_url', null));
    }
}
