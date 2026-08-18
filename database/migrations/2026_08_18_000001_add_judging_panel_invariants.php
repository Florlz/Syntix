<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('judge_scorecards')
            ->select('contest_id', 'entry_id', 'judge_id')
            ->whereNotNull('entry_id')
            ->whereNotNull('judge_id')
            ->groupBy('contest_id', 'entry_id', 'judge_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Duplicate Judge scorecards must be resolved before adding the panel invariant.');
        }

        Schema::table('contests', function (Blueprint $table): void {
            $table->timestamp('judging_panel_locked_at')->nullable();
            $table->foreignId('judging_panel_locked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::table('judge_scorecards', function (Blueprint $table): void {
            $table->unique(['contest_id', 'entry_id', 'judge_id'], 'judge_scorecards_panel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('judge_scorecards', function (Blueprint $table): void {
            $table->dropUnique('judge_scorecards_panel_unique');
        });

        Schema::table('contests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('judging_panel_locked_by');
            $table->dropColumn('judging_panel_locked_at');
        });
    }
};
