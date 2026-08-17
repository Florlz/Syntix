<?php

namespace Tests\Unit;

use App\Enums\DivisionPlacementState;
use App\Enums\ResultSubmissionState;
use App\Models\Competition;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\Event;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\SportWorkspaceReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportWorkspaceReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_are_not_started_without_contests_submissions_or_placements(): void
    {
        [$sport, $division] = $this->workspace();

        $payload = app(SportWorkspaceReadModel::class)->forSport($sport);

        $this->assertSame('not_started', $payload['divisions'][0]['results_state']);
        $this->assertSame(0, $payload['sport']['entry_count']);
        $this->assertSame(0, $payload['divisions'][0]['player_count']);
    }

    public function test_approved_contest_result_without_approved_placement_is_in_progress(): void
    {
        [$sport, $division, $user] = $this->workspace();
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $user->getKey(),
            'state' => ResultSubmissionState::Approved,
            'contest_revision' => 1,
            'payload' => ['outcome_type' => 'played'],
            'approved_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('in_progress', $payload['results_state']);
    }

    public function test_submitted_result_or_placement_needs_review(): void
    {
        [$sport, $division, $user, $rule] = $this->workspace();
        $contest = Contest::factory()->create(['competition_division_id' => $division->getKey()]);
        ResultSubmission::create([
            'contest_id' => $contest->getKey(),
            'submitted_by' => $user->getKey(),
            'state' => ResultSubmissionState::Submitted,
            'contest_revision' => 1,
            'payload' => ['outcome_type' => 'played'],
            'submitted_at' => now(),
        ]);
        DivisionPlacement::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $rule->getKey(),
            'state' => DivisionPlacementState::Submitted,
            'submitted_by' => $user->getKey(),
            'submitted_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('pending_review', $payload['results_state']);
    }

    public function test_only_an_approved_final_placement_completes_results(): void
    {
        [$sport, $division, $user, $rule] = $this->workspace();
        DivisionPlacement::create([
            'competition_division_id' => $division->getKey(),
            'competition_rule_version_id' => $rule->getKey(),
            'state' => DivisionPlacementState::Approved,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);

        $payload = app(SportWorkspaceReadModel::class)->division($division->fresh());

        $this->assertSame('complete', $payload['results_state']);
    }

    /** @return array{0: Competition, 1: Division, 2: User, 3: CompetitionRuleVersion} */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $user->getKey()]);
        $sport = Competition::factory()->create(['event_id' => $event->getKey()]);
        $division = Division::factory()->create(['competition_id' => $sport->getKey()]);
        $rule = CompetitionRuleVersion::create([
            'competition_division_id' => $division->getKey(),
            'version' => 1,
            'lifecycle_state' => 'frozen',
            'is_governing' => true,
            'created_by' => $user->getKey(),
        ]);

        return [$sport, $division, $user, $rule];
    }
}
