<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->string('student_number_normalized')->nullable()->after('student_number');
        });

        DB::table('participants')
            ->whereNotNull('student_number')
            ->orderBy('id')
            ->eachById(function ($participant): void {
                $normalized = strtoupper(trim((string) $participant->student_number));

                DB::table('participants')->where('id', $participant->id)->update([
                    'student_number_normalized' => $normalized === '' ? null : $normalized,
                ]);
            });

        $duplicate = DB::table('participants')
            ->select('event_id', 'student_number_normalized')
            ->whereNotNull('student_number_normalized')
            ->groupBy('event_id', 'student_number_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException('Duplicate normalized student numbers must be resolved before applying registration invariants.');
        }

        Schema::table('participants', function (Blueprint $table): void {
            $table->unique(
                ['event_id', 'student_number_normalized'],
                'participants_event_student_number_unique',
            );
            $table->index(['event_id', 'is_active'], 'participants_event_active_index');
        });

        Schema::table('entry_members', function (Blueprint $table): void {
            $table->index(['entry_id', 'is_active', 'role'], 'entry_members_current_role_index');
        });

        Schema::table('eligibility_records', function (Blueprint $table): void {
            $table->index(['entry_id', 'status'], 'eligibility_entry_status_index');
        });

        $basketballDivisionIds = DB::table('competition_divisions')
            ->join('competitions', 'competitions.id', '=', 'competition_divisions.competition_id')
            ->where('competitions.name', 'Basketball')
            ->pluck('competition_divisions.id');

        if ($basketballDivisionIds->isNotEmpty()) {
            DB::table('competition_rule_versions')
                ->whereIn('competition_division_id', $basketballDivisionIds)
                ->update(['roster_role_limits' => json_encode([
                    'student_coach' => 1,
                    'faculty_coach' => 2,
                ], JSON_THROW_ON_ERROR)]);
        }

        $pairDivisionIds = DB::table('competition_divisions')
            ->join('competitions', 'competitions.id', '=', 'competition_divisions.competition_id')
            ->where('competitions.name', 'Vocal Duet')
            ->pluck('competition_divisions.id');

        if ($pairDivisionIds->isNotEmpty()) {
            DB::table('competition_rule_versions')
                ->whereIn('competition_division_id', $pairDivisionIds)
                ->where('participant_mode', 'pair')
                ->where('max_roster_size', 1)
                ->update(['max_roster_size' => 2]);
        }
    }

    public function down(): void
    {
        Schema::table('eligibility_records', function (Blueprint $table): void {
            $table->dropIndex('eligibility_entry_status_index');
        });

        Schema::table('entry_members', function (Blueprint $table): void {
            $table->dropIndex('entry_members_current_role_index');
        });

        Schema::table('participants', function (Blueprint $table): void {
            $table->dropUnique('participants_event_student_number_unique');
            $table->dropIndex('participants_event_active_index');
            $table->dropColumn('student_number_normalized');
        });
    }
};
