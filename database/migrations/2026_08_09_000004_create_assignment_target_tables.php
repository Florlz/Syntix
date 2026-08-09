<?php

use App\Enums\ContestState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['event_id', 'slug']);
        });

        Schema::create('competition_divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')
                ->constrained('competitions')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['competition_id', 'slug']);
        });

        Schema::create('contests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->string('name');
            $table->enum('state', array_map(
                static fn (ContestState $state): string => $state->value,
                ContestState::cases(),
            ))->default(ContestState::Scheduled->value)->index();
            $table->timestamps();
        });

        // The scorecard target is intentionally narrow. Entry and criterion data
        // belong to the judged-scoring slice; identity authorization only needs
        // the exact scorecard-to-contest containment path.
        Schema::create('judge_scorecards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->restrictOnDelete();
            $table->string('entry_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_scorecards');
        Schema::dropIfExists('contests');
        Schema::dropIfExists('competition_divisions');
        Schema::dropIfExists('competitions');
    }
};
