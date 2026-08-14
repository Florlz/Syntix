<?php

use App\Enums\EventRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_UNIQUE_INDEX = 'event_user_roles_active_unique';

    public function up(): void
    {
        Schema::create('event_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('role', ['admin', 'judge', 'tabulator']);
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'user_id', 'role', 'revoked_at']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::ACTIVE_UNIQUE_INDEX
                .' ON event_user_roles (event_id, user_id, role) WHERE revoked_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::ACTIVE_UNIQUE_INDEX);
        }

        Schema::dropIfExists('event_user_roles');
    }
};
