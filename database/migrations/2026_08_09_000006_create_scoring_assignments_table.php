<?php

use App\Enums\ScoringAssignmentScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DIVISION_UNIQUE_INDEX = 'scoring_assignments_division_active_unique';

    private const CONTEST_UNIQUE_INDEX = 'scoring_assignments_contest_active_unique';

    private const SCORECARD_UNIQUE_INDEX = 'scoring_assignments_scorecard_active_unique';

    public function up(): void
    {
        Schema::create('scoring_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('scope_type', array_map(
                static fn (ScoringAssignmentScope $scope): string => $scope->value,
                ScoringAssignmentScope::cases(),
            ));
            $table->foreignId('competition_division_id')
                ->nullable()
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('contest_id')
                ->nullable()
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('entry_scorecard_id')
                ->nullable()
                ->constrained('judge_scorecards')
                ->restrictOnDelete();
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'user_id', 'scope_type', 'revoked_at']);
        });

        $driver = Schema::getConnection()->getDriverName();
        $exactTarget = "(scope_type = 'competition_division' AND competition_division_id IS NOT NULL AND contest_id IS NULL AND entry_scorecard_id IS NULL) OR "
            ."(scope_type = 'contest' AND competition_division_id IS NULL AND contest_id IS NOT NULL AND entry_scorecard_id IS NULL) OR "
            ."(scope_type = 'entry_scorecard' AND competition_division_id IS NULL AND contest_id IS NULL AND entry_scorecard_id IS NOT NULL)";

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE scoring_assignments ADD CONSTRAINT scoring_assignments_exact_target CHECK ('.$exactTarget.')'
            );
        } elseif ($driver === 'sqlite') {
            $sqliteExactTarget = "(NEW.scope_type = 'competition_division' AND NEW.competition_division_id IS NOT NULL AND NEW.contest_id IS NULL AND NEW.entry_scorecard_id IS NULL) OR "
                ."(NEW.scope_type = 'contest' AND NEW.competition_division_id IS NULL AND NEW.contest_id IS NOT NULL AND NEW.entry_scorecard_id IS NULL) OR "
                ."(NEW.scope_type = 'entry_scorecard' AND NEW.competition_division_id IS NULL AND NEW.contest_id IS NULL AND NEW.entry_scorecard_id IS NOT NULL)";

            DB::statement(
                'CREATE TRIGGER scoring_assignments_exact_target_insert '
                .'BEFORE INSERT ON scoring_assignments WHEN NOT ('.$sqliteExactTarget.') '
                ."BEGIN SELECT RAISE(ABORT, 'scoring assignment must have exactly one target'); END"
            );
            DB::statement(
                'CREATE TRIGGER scoring_assignments_exact_target_update '
                .'BEFORE UPDATE OF scope_type, competition_division_id, contest_id, entry_scorecard_id '
                .'ON scoring_assignments WHEN NOT ('.$sqliteExactTarget.') '
                ."BEGIN SELECT RAISE(ABORT, 'scoring assignment must have exactly one target'); END"
            );
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::DIVISION_UNIQUE_INDEX
                .' ON scoring_assignments (user_id, event_id, competition_division_id)'
                ." WHERE revoked_at IS NULL AND scope_type = '".ScoringAssignmentScope::CompetitionDivision->value."'"
            );
            DB::statement(
                'CREATE UNIQUE INDEX '.self::CONTEST_UNIQUE_INDEX
                .' ON scoring_assignments (user_id, event_id, contest_id)'
                ." WHERE revoked_at IS NULL AND scope_type = '".ScoringAssignmentScope::Contest->value."'"
            );
            DB::statement(
                'CREATE UNIQUE INDEX '.self::SCORECARD_UNIQUE_INDEX
                .' ON scoring_assignments (user_id, event_id, entry_scorecard_id)'
                ." WHERE revoked_at IS NULL AND scope_type = '".ScoringAssignmentScope::EntryScorecard->value."'"
            );
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE scoring_assignments DROP CONSTRAINT IF EXISTS scoring_assignments_exact_target');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS scoring_assignments_exact_target_insert');
            DB::statement('DROP TRIGGER IF EXISTS scoring_assignments_exact_target_update');
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::DIVISION_UNIQUE_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::CONTEST_UNIQUE_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::SCORECARD_UNIQUE_INDEX);
        }

        Schema::dropIfExists('scoring_assignments');
    }
};
