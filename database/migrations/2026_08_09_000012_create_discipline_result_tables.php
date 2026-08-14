<?php

use App\Enums\DisciplineResultState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')
                ->constrained('disciplines')
                ->restrictOnDelete();
            $table->foreignId('contest_id')
                ->nullable()
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->decimal('performance_value', 18, 6)->nullable();
            $table->string('unit')->nullable();
            $table->string('qualification_status')->nullable();
            $table->enum('state', ['draft', 'submitted', 'approved', 'voided'])
                ->default(DisciplineResultState::Draft->value)->index();
            $table->unsignedBigInteger('revision')->default(0);
            $table->json('payload')->nullable();
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['discipline_id', 'entry_id', 'state']);
        });

        Schema::create('discipline_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')
                ->constrained('disciplines')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->unsignedInteger('rank');
            $table->decimal('sub_points', 14, 4)->default(0);
            $table->enum('state', ['submitted', 'approved', 'voided'])
                ->default(DisciplineResultState::Submitted->value)->index();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['discipline_id', 'entry_id']);
            $table->unique(['discipline_id', 'rank']);
        });

        Schema::create('division_sub_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('discipline_placement_id')
                ->constrained('discipline_placements')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->decimal('amount', 14, 4);
            $table->string('source_key')->unique();
            $table->timestamp('committed_at');
            $table->timestamps();
            $table->index(['competition_division_id', 'event_delegation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_sub_points');
        Schema::dropIfExists('discipline_placements');
        Schema::dropIfExists('discipline_results');
    }
};
