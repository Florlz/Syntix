<?php

namespace Tests\Feature\Event;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\RuleVersionState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\SiklabReferenceSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Siklab2025ProgrammeTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_can_apply_the_proposal_programme_idempotently(): void
    {
        $this->seed(SiklabReferenceSeeder::class);
        $admin = (new BootstrapGlobalAdmin)->handle([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ]);
        $event = Event::factory()->create(['created_by' => $admin->getKey()]);
        $apply = new ApplySiklab2025Programme;

        $apply->handle($admin, $event);
        $apply->handle($admin, $event);

        $this->assertSame(7, $event->delegations()->count());
        $this->assertSame(33, $event->competitions()->count());
        $this->assertDatabaseHas('event_delegations', [
            'event_id' => $event->getKey(),
            'abbreviation' => 'CTHBM',
        ]);

        $basketball = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'basketball')
            ->firstOrFail();
        $basketballMen = $basketball->divisions()->where('slug', 'men')->firstOrFail();
        $this->assertSame(7, $basketballMen->entries()->count());
        $this->assertSame('single_elimination', $basketballMen->governingRuleVersion()->firstOrFail()->format()->value);

        $chess = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'chess')
            ->firstOrFail();
        $this->assertSame('round_robin', $chess->divisions()->where('slug', 'women')->firstOrFail()
            ->governingRuleVersion()->firstOrFail()->format()->value);

        $athletics = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'athletics')
            ->firstOrFail();
        $athleticsMen = $athletics->divisions()->where('slug', 'men')->firstOrFail();
        $this->assertSame(12, $athleticsMen->disciplines()->count());
        $this->assertSame(
            RuleVersionState::Draft,
            $athleticsMen->ruleVersions()->firstOrFail()->lifecycleState(),
        );

        $extemporaneous = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'extemporaneous-speaking')
            ->firstOrFail();
        $extemporaneousRule = $extemporaneous->divisions()->firstOrFail()->governingRuleVersion()->firstOrFail();
        $this->assertSame(4, $extemporaneousRule->criteria()->count());
        $this->assertSame(100, $extemporaneousRule->criteria()->sum('weight'));

        $essay = Competition::query()
            ->whereBelongsTo($event)
            ->where('slug', 'essay-writing')
            ->firstOrFail();
        $essayRule = $essay->divisions()->firstOrFail()->ruleVersions()->firstOrFail();
        $this->assertSame(RuleVersionState::Draft, $essayRule->lifecycleState());
        $this->assertSame('blocked', $essayRule->source_status);
        $this->assertSame(95, $essayRule->criteria()->sum('weight'));

        $this->assertSame(42, CompetitionRuleVersion::query()->count());
        $this->assertDatabaseCount('scoring_criteria', 92);
        $this->assertDatabaseCount('audit_logs', 39);
    }

    public function test_non_global_user_cannot_apply_the_programme(): void
    {
        $this->seed(SiklabReferenceSeeder::class);
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new ApplySiklab2025Programme)->handle($user, $event);
    }
}
