<?php

use App\Enums\DivisionPlacementState;
use App\Enums\LedgerEntryType;
use App\Enums\OfficialOutcomeState;
use App\Enums\OutcomeType;
use App\Enums\ResultSubmissionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table): void {
            $table->foreignId('discipline_id')
                ->nullable()
                ->after('competition_division_id')
                ->constrained('disciplines')
                ->restrictOnDelete();
            $table->foreignId('competition_rule_version_id')
                ->nullable()
                ->after('discipline_id')
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->unsignedBigInteger('revision')->default(0)->index();
            $table->json('live_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
        });

        Schema::create('contest_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->unsignedInteger('slot')->nullable();
            $table->string('state')->default('active');
            $table->timestamps();
            $table->unique(['contest_id', 'entry_id']);
            $table->unique(['contest_id', 'slot']);
        });

        Schema::create('result_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('submitted_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('state', ['draft', 'completed', 'submitted', 'rejected', 'approved'])
                ->default(ResultSubmissionState::Draft->value)->index();
            $table->unsignedBigInteger('contest_revision');
            $table->json('payload');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['contest_id', 'state']);
        });

        Schema::create('official_contest_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('result_submission_id')
                ->constrained('result_submissions')
                ->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->enum('state', ['approved', 'superseded', 'voided'])
                ->default(OfficialOutcomeState::Approved->value)->index();
            $table->enum('outcome_type', [
                'played', 'walkover', 'forfeit', 'no_show', 'withdrawal',
                'disqualification', 'ruled',
            ]);
            $table->foreignId('winner_entry_id')
                ->nullable()
                ->constrained('entries')
                ->restrictOnDelete();
            $table->json('payload');
            $table->foreignId('approved_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['contest_id', 'state']);
        });

        Schema::create('division_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('competition_rule_version_id')
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->unsignedBigInteger('revision')->default(1);
            $table->enum('state', ['candidate', 'submitted', 'rejected', 'approved', 'superseded', 'voided'])
                ->default(DivisionPlacementState::Candidate->value)->index();
            $table->json('evidence')->nullable();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['competition_division_id', 'state']);
        });

        Schema::create('division_placement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_placement_id')
                ->constrained('division_placements')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->unsignedInteger('rank');
            $table->string('placement_key');
            $table->decimal('championship_points', 14, 4)->default(0);
            $table->boolean('participation_eligible')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['division_placement_id', 'entry_id']);
            $table->unique(['division_placement_id', 'rank']);
        });

        Schema::create('score_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->foreignId('division_placement_id')
                ->constrained('division_placements')
                ->restrictOnDelete();
            $table->foreignId('division_placement_item_id')
                ->constrained('division_placement_items')
                ->restrictOnDelete();
            $table->enum('entry_type', ['award', 'reversal', 'replacement']);
            $table->decimal('amount', 14, 4);
            $table->string('source_key')->unique();
            $table->unsignedBigInteger('source_revision');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('committed_at');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'event_delegation_id', 'committed_at']);
        });

        Schema::create('scoring_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('command_uuid')->unique();
            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->unsignedInteger('schema_version');
            $table->string('command_type');
            $table->string('disposition');
            $table->string('envelope_hash', 64);
            $table->unsignedBigInteger('base_revision')->nullable();
            $table->uuid('depends_on_command_uuid')->nullable();
            $table->json('canonical_envelope');
            $table->json('response')->nullable();
            $table->unsignedBigInteger('resulting_revision')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['actor_id', 'event_id', 'created_at']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX official_contest_outcomes_current_unique '
                .'ON official_contest_outcomes (contest_id) WHERE state = \'approved\''
            );
            DB::statement(
                'CREATE UNIQUE INDEX division_placements_current_unique '
                .'ON division_placements (competition_division_id) WHERE state = \'approved\''
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS official_contest_outcomes_current_unique');
            DB::statement('DROP INDEX IF EXISTS division_placements_current_unique');
        }

        Schema::dropIfExists('scoring_command_receipts');
        Schema::dropIfExists('score_ledger_entries');
        Schema::dropIfExists('division_placement_items');
        Schema::dropIfExists('division_placements');
        Schema::dropIfExists('official_contest_outcomes');
        Schema::dropIfExists('result_submissions');
        Schema::dropIfExists('contest_entries');

        Schema::table('contests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discipline_id');
            $table->dropConstrainedForeignId('competition_rule_version_id');
            $table->dropColumn([
                'revision',
                'live_payload',
                'result_payload',
                'started_at',
                'completed_at',
                'completed_by',
                'cancelled_at',
                'cancel_reason',
            ]);
        });
    }
};
