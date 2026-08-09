<?php

use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\TournamentState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('competition_rule_version_id')
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->enum('format', array_map(
                static fn (CompetitionFormat $format): string => $format->value,
                [CompetitionFormat::SingleElimination, CompetitionFormat::DoubleElimination, CompetitionFormat::RoundRobin],
            ));
            $table->enum('state', array_map(
                static fn (TournamentState $state): string => $state->value,
                TournamentState::cases(),
            ))->default(TournamentState::Draft->value)->index();
            $table->unsignedInteger('eligible_entry_count')->default(0);
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('draw_locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_division_id', 'id']);
        });

        Schema::create('draw_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')
                ->constrained('tournaments')
                ->restrictOnDelete();
            $table->json('draw_order');
            $table->string('source');
            $table->foreignId('confirmed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });

        Schema::create('bracket_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')
                ->constrained('tournaments')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->enum('state', array_map(
                static fn (BracketVersionState $state): string => $state->value,
                BracketVersionState::cases(),
            ))->default(BracketVersionState::Preview->value)->index();
            $table->string('generation_algorithm_version');
            $table->json('draw_order');
            $table->json('generation_inputs');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'version']);
        });

        Schema::create('bracket_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bracket_version_id')
                ->constrained('bracket_versions')
                ->restrictOnDelete();
            $table->string('node_key');
            $table->enum('node_type', array_map(
                static fn (BracketNodeType $type): string => $type->value,
                BracketNodeType::cases(),
            ));
            $table->unsignedInteger('round_number')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('state')->default('pending');
            $table->foreignId('contest_id')
                ->nullable()
                ->constrained('contests')
                ->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['bracket_version_id', 'node_key']);
        });

        Schema::create('bracket_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bracket_node_id')
                ->constrained('bracket_nodes')
                ->restrictOnDelete();
            $table->unsignedInteger('slot_number');
            $table->foreignId('entry_id')
                ->nullable()
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('source_node_id')
                ->nullable()
                ->constrained('bracket_nodes')
                ->restrictOnDelete();
            $table->string('source_result')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['bracket_node_id', 'slot_number']);
        });

        Schema::create('advancement_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bracket_node_id')
                ->constrained('bracket_nodes')
                ->restrictOnDelete();
            $table->string('outcome');
            $table->foreignId('target_slot_id')
                ->constrained('bracket_slots')
                ->restrictOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['bracket_node_id', 'outcome', 'target_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advancement_rules');
        Schema::dropIfExists('bracket_slots');
        Schema::dropIfExists('bracket_nodes');
        Schema::dropIfExists('bracket_versions');
        Schema::dropIfExists('draw_records');
        Schema::dropIfExists('tournaments');
    }
};
