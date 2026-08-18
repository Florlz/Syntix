<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->restrictOnDelete();
            $table->foreignId('entry_id')->constrained('entries')->restrictOnDelete();
            $table->foreignId('competition_rule_version_id')->constrained('competition_rule_versions')->restrictOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('source_reference');
            $table->decimal('input_value', 14, 4);
            $table->string('input_unit');
            $table->decimal('points', 14, 4);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['contest_id', 'entry_id', 'code']);
            $table->index(['contest_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_adjustments');
    }
};
