<?php

use App\Enums\CompetitionFormat;
use App\Enums\CriterionNumberMeaning;
use App\Enums\DisciplineFamily;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\ParticipantMode;
use App\Enums\RosterMemberRole;
use App\Enums\RuleVersionState;
use App\Enums\ScheduleStatus;
use App\Enums\ScoringFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->string('default_color')->nullable()->after('slug');
        });

        Schema::table('competition_divisions', function (Blueprint $table): void {
            $table->timestamp('scoring_started_at')->nullable()->index();
        });

        Schema::create('placement_point_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('source_reference')->nullable();
            $table->boolean('is_signed_off')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('placement_point_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('placement_point_template_id')
                ->constrained('placement_point_templates')
                ->restrictOnDelete();
            $table->string('placement_key');
            $table->decimal('points', 12, 4);
            $table->boolean('is_participation')->default(false);
            $table->json('eligibility_conditions')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['placement_point_template_id', 'placement_key']);
        });

        Schema::create('competition_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('placement_point_template_id')
                ->nullable()
                ->constrained('placement_point_templates')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->enum('lifecycle_state', ['draft', 'activated_editable', 'frozen', 'superseded', 'archived'])
                ->default(RuleVersionState::Draft->value);
            $table->boolean('is_governing')->default(false)->index();
            $table->enum('scoring_family', ['objective', 'criteria_based', 'aggregate', 'custom_series'])->nullable();
            $table->enum('format', [
                'single_elimination', 'double_elimination', 'round_robin', 'series',
                'placement', 'criteria_based', 'aggregate', 'custom',
            ])->nullable();
            $table->enum('participant_mode', ['team', 'individual', 'pair', 'relay', 'mixed'])->nullable();
            $table->unsignedInteger('min_roster_size')->nullable();
            $table->unsignedInteger('max_roster_size')->nullable();
            $table->unsignedInteger('entries_per_delegation')->nullable();
            $table->unsignedInteger('participant_competition_limit')->nullable();
            $table->json('roster_role_limits')->nullable();
            $table->enum('criteria_calculation_mode', ['percentage_weight', 'point_maximum'])->nullable();
            $table->decimal('verified_scorecard_total', 14, 4)->nullable();
            $table->string('judge_aggregation_method')->nullable();
            $table->json('deduction_configuration')->nullable();
            $table->unsignedTinyInteger('input_scale')->nullable();
            $table->unsignedTinyInteger('calculation_scale')->nullable();
            $table->unsignedTinyInteger('display_scale')->nullable();
            $table->unsignedTinyInteger('comparison_scale')->nullable();
            $table->string('rounding_mode')->nullable();
            $table->string('rounding_stage')->nullable();
            $table->json('tie_breaker_configuration')->nullable();
            $table->json('participation_configuration')->nullable();
            $table->json('publication_configuration')->nullable();
            $table->json('approval_configuration')->nullable();
            $table->json('scoring_configuration')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('source_status')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('activated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('supersedes_id')
                ->nullable()
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_division_id', 'version']);
            $table->index(['competition_division_id', 'lifecycle_state']);
        });

        Schema::create('scoring_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_rule_version_id')
                ->constrained('competition_rule_versions')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('source_label');
            $table->unsignedInteger('display_order');
            $table->enum('number_meaning', ['percentage_weight', 'point_maximum']);
            $table->decimal('weight', 10, 4)->nullable();
            $table->decimal('maximum_points', 14, 4)->nullable();
            $table->decimal('raw_minimum', 14, 4)->nullable();
            $table->decimal('raw_maximum', 14, 4)->nullable();
            $table->unsignedTinyInteger('input_scale')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('deduction_configuration')->nullable();
            $table->string('source_page')->nullable();
            $table->string('transcription_status')->nullable();
            $table->string('reviewer')->nullable();
            $table->string('approval_reference')->nullable();
            $table->timestamps();
            $table->unique(['competition_rule_version_id', 'display_order']);
        });

        Schema::create('disciplines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            // Combat was introduced by the additive scope migration; keep
            // this original schema list frozen at its historical values.
            $table->enum('family', ['track', 'field', 'relay']);
            $table->string('performance_type');
            $table->string('canonical_unit');
            $table->json('accepted_input_units')->nullable();
            $table->string('sort_direction');
            $table->unsignedTinyInteger('input_scale')->nullable();
            $table->unsignedTinyInteger('storage_scale')->nullable();
            $table->unsignedTinyInteger('display_scale')->nullable();
            $table->json('qualification_configuration')->nullable();
            $table->json('tie_breaker_configuration')->nullable();
            $table->json('sub_point_configuration')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['competition_division_id', 'code']);
        });

        Schema::create('participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->string('display_name');
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('student_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('private_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'event_delegation_id']);
        });

        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('event_delegation_id')
                ->constrained('event_delegations')
                ->restrictOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->enum('entry_mode', ['team', 'individual', 'pair', 'relay', 'mixed']);
            $table->enum('status', ['draft', 'active', 'locked', 'withdrawn', 'disqualified'])
                ->default(EntryStatus::Draft->value)->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->index(['competition_division_id', 'event_delegation_id']);
        });

        Schema::create('entry_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->restrictOnDelete();
            $table->enum('role', ['student_athlete', 'reserve', 'student_coach', 'faculty_coach']);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['entry_id', 'participant_id']);
        });

        Schema::create('eligibility_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->restrictOnDelete();
            $table->foreignId('participant_id')
                ->constrained('participants')
                ->restrictOnDelete();
            $table->enum('status', ['pending', 'eligible', 'ineligible', 'withdrawn', 'disqualified'])
                ->default(EligibilityStatus::Pending->value);
            $table->text('reason')->nullable();
            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['entry_id', 'participant_id']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('competition_division_id')
                ->constrained('competition_divisions')
                ->restrictOnDelete();
            $table->foreignId('discipline_id')
                ->nullable()
                ->constrained('disciplines')
                ->restrictOnDelete();
            $table->foreignId('contest_id')
                ->nullable()
                ->constrained('contests')
                ->restrictOnDelete();
            $table->foreignId('venue_id')
                ->nullable()
                ->constrained('venues')
                ->restrictOnDelete();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['scheduled', 'postponed', 'cancelled', 'completed'])
                ->default(ScheduleStatus::Scheduled->value)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'starts_at']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX competition_rule_versions_one_governing '
                .'ON competition_rule_versions (competition_division_id) '
                .'WHERE is_governing = TRUE'
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS competition_rule_versions_one_governing');
        }

        Schema::dropIfExists('schedules');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('eligibility_records');
        Schema::dropIfExists('entry_members');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('disciplines');
        Schema::dropIfExists('scoring_criteria');
        Schema::dropIfExists('competition_rule_versions');
        Schema::dropIfExists('placement_point_rules');
        Schema::dropIfExists('placement_point_templates');

        Schema::table('competition_divisions', function (Blueprint $table): void {
            $table->dropColumn('scoring_started_at');
        });

        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->dropColumn('default_color');
        });
    }
};
