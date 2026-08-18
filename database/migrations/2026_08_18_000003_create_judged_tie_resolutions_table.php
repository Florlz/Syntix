<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judged_tie_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->restrictOnDelete();
            $table->json('tied_entry_ids');
            $table->json('authorized_order');
            $table->string('comparison_total', 50);
            $table->text('reason');
            $table->string('reference');
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at');
            $table->timestamps();
            $table->index(['contest_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judged_tie_resolutions');
    }
};
