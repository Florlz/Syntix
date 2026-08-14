<?php

use App\Enums\PublicationState;
use App\Enums\ScheduleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_cover_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')
                ->constrained('competitions')
                ->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('private_path');
            $table->string('public_path')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text', 180);
            $table->enum('state', ['draft', 'published', 'superseded', 'withdrawn'])
                ->default(PublicationState::Draft->value)->index();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('withdrawn_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'revision']);
            $table->index(['competition_id', 'state']);
        });

        Schema::create('schedule_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('competition_name');
            $table->string('division_name');
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['scheduled', 'postponed', 'cancelled', 'completed']);
            $table->string('venue_name')->nullable();
            $table->string('venue_location')->nullable();
            $table->enum('state', ['draft', 'published', 'superseded', 'withdrawn'])
                ->default(PublicationState::Published->value)->index();
            $table->foreignId('published_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('published_at');
            $table->foreignId('withdrawn_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'revision']);
            $table->index(['schedule_id', 'state']);
            $table->index(['state', 'starts_at']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX competition_cover_images_one_draft '
                ."ON competition_cover_images (competition_id) WHERE state = 'draft'"
            );
            DB::statement(
                'CREATE UNIQUE INDEX competition_cover_images_one_published '
                ."ON competition_cover_images (competition_id) WHERE state = 'published'"
            );
            DB::statement(
                'CREATE UNIQUE INDEX schedule_publications_one_published '
                ."ON schedule_publications (schedule_id) WHERE state = 'published'"
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS schedule_publications_one_published');
            DB::statement('DROP INDEX IF EXISTS competition_cover_images_one_published');
            DB::statement('DROP INDEX IF EXISTS competition_cover_images_one_draft');
        }

        Schema::dropIfExists('schedule_publications');
        Schema::dropIfExists('competition_cover_images');
    }
};
