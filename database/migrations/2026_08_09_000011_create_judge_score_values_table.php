<?php

use App\Enums\ScorecardState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judge_scorecards', function (Blueprint $table): void {
            $table->foreignId('entry_id')
                ->nullable()
                ->after('contest_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('judge_id')
                ->nullable()
                ->after('entry_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('competition_rule_version_id')
                ->nullable()
                ->after('judge_id')
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->enum('state', array_map(
                static fn (ScorecardState $state): string => $state->value,
                ScorecardState::cases(),
            ))->default(ScorecardState::Draft->value)->index();
            $table->unsignedBigInteger('revision')->default(0);
            $table->decimal('calculated_total', 14, 4)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->index(['contest_id', 'entry_id', 'judge_id']);
        });

        Schema::create('judge_score_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('judge_scorecard_id')
                ->constrained('judge_scorecards')
                ->restrictOnDelete();
            $table->foreignId('scoring_criterion_id')
                ->constrained('scoring_criteria')
                ->restrictOnDelete();
            $table->decimal('raw_value', 14, 4);
            $table->decimal('deduction', 14, 4)->default(0);
            $table->decimal('net_value', 14, 4);
            $table->decimal('weighted_value', 14, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['judge_scorecard_id', 'scoring_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_score_values');
        Schema::table('judge_scorecards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entry_id');
            $table->dropConstrainedForeignId('judge_id');
            $table->dropConstrainedForeignId('competition_rule_version_id');
            $table->dropColumn([
                'state',
                'revision',
                'calculated_total',
                'payload',
                'submitted_at',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
