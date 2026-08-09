<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GLOBAL_ADMIN_INDEX = 'users_single_global_admin';

    private const TEAM_ENTRY_INDEX = 'entries_current_team_per_delegation_unique';

    public function up(): void
    {
        if (DB::table('users')->where('is_global_admin', true)->count() > 1) {
            throw new RuntimeException('Resolve duplicate Global Admin accounts before running this migration.');
        }

        $now = now();

        DB::table('event_user_roles')
            ->where('role', 'admin')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'reason' => 'Legacy Event Admin retired in favor of the sole Global Admin.',
                'updated_at' => $now,
            ]);

        DB::table('platform_capability_grants')
            ->where('capability', 'event_creator')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'reason' => 'Legacy event_creator retired in favor of the sole Global Admin.',
                'updated_at' => $now,
            ]);

        Schema::table('draw_records', function (Blueprint $table): void {
            $table->uuid('command_uuid')->nullable()->unique()->after('tournament_id');
            $table->text('random_seed')->nullable()->after('draw_order');
            $table->string('algorithm_version')->nullable()->after('random_seed');
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::GLOBAL_ADMIN_INDEX
                .' ON users (is_global_admin) WHERE is_global_admin = TRUE'
            );
            DB::statement(
                'CREATE UNIQUE INDEX '.self::TEAM_ENTRY_INDEX
                .' ON entries (competition_division_id, event_delegation_id)'
                ." WHERE entry_mode = 'team' AND status IN ('draft', 'active', 'locked')"
            );
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::TEAM_ENTRY_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::GLOBAL_ADMIN_INDEX);
        }

        Schema::table('draw_records', function (Blueprint $table): void {
            $table->dropUnique(['command_uuid']);
            $table->dropColumn(['command_uuid', 'random_seed', 'algorithm_version']);
        });
    }
};
