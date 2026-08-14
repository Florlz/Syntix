<?php

use App\Enums\EventState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Keep this historical schema literal stable if EventState gains
            // a future application-only state.
            $table->enum('state', ['preparation', 'configuration', 'live', 'closed', 'archived'])
                ->default(EventState::Preparation->value)->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organizational_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('abbreviation')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('event_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('abbreviation')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['event_id', 'organizational_unit_id']);
            $table->unique(['event_id', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_delegations');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('events');
    }
};
