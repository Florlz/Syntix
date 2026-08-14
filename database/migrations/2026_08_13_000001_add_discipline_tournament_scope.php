<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->foreignId('discipline_id')
                ->nullable()
                ->after('competition_division_id')
                ->constrained('disciplines')
                ->restrictOnDelete();
            $table->index(['competition_division_id', 'discipline_id', 'state']);
        });

        Schema::create('discipline_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->foreignId('entry_id')->constrained('entries')->restrictOnDelete();
            $table->foreignId('event_delegation_id')->constrained('event_delegations')->restrictOnDelete();
            $table->string('state')->default('draft')->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->unique(['discipline_id', 'event_delegation_id']);
            $table->unique(['discipline_id', 'entry_id']);
        });

        Schema::create('discipline_entry_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_entry_id')->constrained('discipline_entries')->restrictOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->boolean('is_starter')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['discipline_entry_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_entry_members');
        Schema::dropIfExists('discipline_entries');
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropForeign(['discipline_id']);
            $table->dropIndex(['competition_division_id', 'discipline_id', 'state']);
            $table->dropColumn('discipline_id');
        });
    }
};
