<?php

namespace Tests\Feature\Backend;

use App\Enums\AdvancementOutcome;
use App\Enums\BracketNodeState;
use App\Enums\BracketSlotSource;
use App\Enums\DisciplineEntryState;
use App\Enums\DisciplinePlacementState;
use App\Enums\TournamentFormat;
use App\Models\AdvancementRule;
use App\Models\BracketNode;
use App\Models\BracketSlot;
use App\Models\DisciplineEntry;
use App\Models\DisciplinePlacement;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The contract suite requires PostgreSQL.');
        }
    }

    public function test_bounded_model_casts_match_database_contract(): void
    {
        self::assertSame(TournamentFormat::class, (new Tournament)->getCasts()['format']);
        self::assertSame(DisciplinePlacementState::class, (new DisciplinePlacement)->getCasts()['state']);
        self::assertSame(DisciplineEntryState::class, (new DisciplineEntry)->getCasts()['state']);
        self::assertSame(BracketNodeState::class, (new BracketNode)->getCasts()['state']);
        self::assertSame(AdvancementOutcome::class, (new AdvancementRule)->getCasts()['outcome']);
        self::assertSame(BracketSlotSource::class, (new BracketSlot)->getCasts()['source_result']);
    }

    public function test_user_preferences_round_trip_as_nullable_json(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'text_size' => 'large',
                'contrast' => 'high',
                'reduce_motion' => true,
                'default_event_id' => null,
                'default_landing' => 'overview',
            ],
        ]);

        $preferences = $user->fresh()->preferences;

        self::assertSame('large', $preferences['text_size']);
        self::assertTrue($preferences['reduce_motion']);
        self::assertNull($preferences['default_event_id']);

        $legacy = User::factory()->create(['preferences' => null]);
        self::assertSame(User::DEFAULT_PREFERENCES, $legacy->fresh()->normalizedPreferences(collect()));
    }

    public function test_state_constraints_are_present_with_frozen_values(): void
    {
        $constraints = DB::table('pg_constraint')
            ->whereIn('conname', [
                'tournaments_format_bounded_check',
                'discipline_placements_state_bounded_check',
                'discipline_entries_state_bounded_check',
                'bracket_nodes_state_bounded_check',
                'advancement_rules_outcome_bounded_check',
                'bracket_slots_source_result_bounded_check',
            ])
            ->pluck('conname')
            ->all();

        self::assertCount(6, $constraints);
    }
}
