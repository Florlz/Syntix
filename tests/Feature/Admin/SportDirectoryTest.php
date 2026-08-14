<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\Competition;
use App\Models\Event;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sports_directory_emits_a_compact_operational_card_contract(): void
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Directory Admin',
            'email' => 'directory-'.uniqid().'@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);
        $basketball = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.sports.index', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Sports/Index')
                ->where('sports', fn ($sports): bool => collect($sports)->contains(fn ($sport): bool => $sport['id'] === (string) $basketball->getKey()
                    && $sport['cover_state'] === 'No image'
                    && array_key_exists('division_count', $sport)
                    && array_key_exists('locked_entries', $sport)
                    && array_key_exists('player_count', $sport)
                    && array_key_exists('next_activity', $sport)
                    && array_key_exists('attention', $sport))));
    }
}
