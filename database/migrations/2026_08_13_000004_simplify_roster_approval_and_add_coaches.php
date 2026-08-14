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
            $table->boolean('is_competitor')->default(true)->index()->after('is_active');
        });
        Schema::table('competitions', function (Blueprint $table): void {
            $table->string('programme_family')->nullable()->index()->after('slug');
        });

        Schema::create('coach_capacity_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('scope_type');
            $table->string('scope_key');
            $table->unsignedInteger('student_coach_max')->nullable();
            $table->unsignedInteger('faculty_coach_max')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'scope_type', 'scope_key'], 'coach_capacity_scope_unique');
        });
        Schema::create('coach_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('event_delegation_id')->constrained('event_delegations')->restrictOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->string('coach_type');
            $table->string('title')->nullable();
            $table->string('scope_type');
            $table->string('scope_key');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'event_delegation_id', 'scope_type', 'scope_key'], 'coach_assignment_scope_index');
        });
        Schema::create('roster_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('entry_id')->constrained('entries')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('players_snapshot');
            $table->json('coaches_snapshot');
            $table->json('limits_snapshot');
            $table->json('source_context')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['entry_id', 'revision']);
        });
        Schema::create('participation_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('entry_id')->constrained('entries')->restrictOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->string('type');
            $table->text('reason');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('legacy_eligibility_record_id')->nullable()->unique()->constrained('eligibility_records')->restrictOnDelete();
            $table->timestamps();
            $table->index(['entry_id', 'participant_id']);
        });

        $families = [
            'Extemporaneous Speaking' => 'literary', 'Dagliang Talumpati' => 'literary', 'Essay Writing' => 'literary', 'Pagsulat ng Sanaysay' => 'literary', 'Story Telling' => 'literary', 'Pagkukwento' => 'literary', 'Radio Drama' => 'literary',
            'Pop Solo' => 'musical', 'Kundiman' => 'musical', 'Vocal Duet' => 'musical',
            'Folk Dance' => 'dance', 'Hip Hop Dance' => 'dance', 'Contemporary Dance' => 'dance', 'Dance Sports' => 'dance',
            'Charcoal Rendering' => 'visual_arts', 'Pencil Drawing' => 'visual_arts', 'Painting' => 'visual_arts', 'On-the-Spot Poster Making' => 'visual_arts', 'Photography' => 'visual_arts',
        ];
        foreach ($families as $name => $family) DB::table('competitions')->where('name', $name)->update(['programme_family' => $family]);

        DB::table('entry_members')->whereIn('role', ['student_coach', 'faculty_coach'])->orderBy('id')->eachById(function ($member): void {
            $entry = DB::table('entries')->where('id', $member->entry_id)->first();
            $participant = DB::table('participants')->where('id', $member->participant_id)->first();
            if (! $entry || ! $participant) return;
            $eventId = DB::table('competition_divisions')->join('competitions', 'competitions.id', '=', 'competition_divisions.competition_id')->where('competition_divisions.id', $entry->competition_division_id)->value('competitions.event_id');
            DB::table('coach_assignments')->insert([
                'event_id' => $eventId, 'event_delegation_id' => $entry->event_delegation_id, 'participant_id' => $member->participant_id,
                'coach_type' => $member->role, 'title' => 'Coach', 'scope_type' => 'competition_division', 'scope_key' => (string) $entry->competition_division_id,
                'is_active' => (bool) $member->is_active, 'notes' => $member->notes, 'created_at' => now(), 'updated_at' => now(), 'deactivated_at' => $member->is_active ? null : now(),
            ]);
            DB::table('entry_members')->where('id', $member->id)->update(['is_active' => false, 'notes' => trim(($member->notes ? $member->notes.' ' : '').'Migrated to coach assignment.')]);
            $hasPlayerRole = DB::table('entry_members')->where('participant_id', $member->participant_id)->whereIn('role', ['student_athlete', 'reserve'])->exists();
            if (! $hasPlayerRole) DB::table('participants')->where('id', $member->participant_id)->update(['is_competitor' => false]);
        });

        DB::table('eligibility_records')->whereIn('status', ['ineligible', 'withdrawn', 'disqualified'])->orderBy('id')->eachById(function ($record): void {
            DB::table('participation_exceptions')->insert([
                'event_id' => $record->event_id, 'entry_id' => $record->entry_id, 'participant_id' => $record->participant_id, 'type' => $record->status,
                'reason' => $record->reason ?: 'Migrated legacy eligibility decision.', 'recorded_by' => $record->checked_by, 'recorded_at' => $record->checked_at ?: $record->updated_at ?: now(),
                'legacy_eligibility_record_id' => $record->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('entry_members')->where('entry_id', $record->entry_id)->where('participant_id', $record->participant_id)->update(['is_active' => false]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_exceptions');
        Schema::dropIfExists('roster_approvals');
        Schema::dropIfExists('coach_assignments');
        Schema::dropIfExists('coach_capacity_rules');
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn('programme_family'));
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('is_competitor'));
    }
};
