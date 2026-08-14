<?php

namespace Tests\Feature\Admin;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_a_sport_workspace_with_explicit_tab_and_division_state(): void
    {
        [$admin, $event] = $this->programme();
        $sport = Competition::query()->whereBelongsTo($event)->where('slug', 'basketball')->firstOrFail();
        $division = $sport->divisions()->where('slug', 'men')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.sports.show', [$event, $sport]).'?tab=rosters&division='.$division->getKey())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Sports/Workspace')
                ->where('sport.id', (string) $sport->getKey())
                ->where('active_tab', 'rosters')
                ->where('selected_division', (string) $division->getKey())
                ->has('divisions'));
    }

    public function test_foreign_division_is_not_available_inside_a_sport_workspace(): void
    {
        [$admin, $event] = $this->programme();
        $sports = Competition::query()->whereBelongsTo($event)->orderBy('id')->get();
        $sport = $sports->firstOrFail();
        $foreignDivision = Division::query()->where('competition_id', '!=', $sport->getKey())->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.sports.show', [$event, $sport]).'?division='.$foreignDivision->getKey())
            ->assertNotFound();
    }

    public function test_non_admin_cannot_open_a_sport_workspace(): void
    {
        [$admin, $event] = $this->programme();
        $sport = Competition::query()->whereBelongsTo($event)->firstOrFail();
        $worker = User::factory()->create();

        $this->actingAs($worker)->get(route('admin.sports.show', [$event, $sport]))->assertForbidden();
    }

    /** @return array{0: User, 1: Event} */
    private function programme(): array
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Workspace Admin',
            'email' => 'sport-workspace-'.uniqid().'@example.test',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        (new ApplySiklab2025Programme)->handle($admin, $event);

        return [$admin, $event];
    }
}
