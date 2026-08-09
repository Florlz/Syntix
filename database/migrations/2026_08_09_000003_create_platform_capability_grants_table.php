<?php

use App\Enums\PlatformCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_UNIQUE_INDEX = 'platform_capability_grants_active_unique';

    public function up(): void
    {
        Schema::create('platform_capability_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('capability', [PlatformCapability::EventCreator->value]);
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
            $table->index(['user_id', 'capability', 'revoked_at']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::ACTIVE_UNIQUE_INDEX
                .' ON platform_capability_grants (user_id, capability) WHERE revoked_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::ACTIVE_UNIQUE_INDEX);
        }

        Schema::dropIfExists('platform_capability_grants');
    }
};
