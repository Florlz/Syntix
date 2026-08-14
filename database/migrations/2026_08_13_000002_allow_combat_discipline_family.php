<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite stores Laravel enum values as a table CHECK constraint.
            // Re-declaring the column as a string makes the additive value
            // available to the fast SQLite test database without rewriting
            // the historical migration that created the table.
            Schema::table('disciplines', function (Blueprint $table): void {
                $table->string('family')->change();
            });

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE disciplines DROP CONSTRAINT IF EXISTS disciplines_family_check');
        DB::statement("ALTER TABLE disciplines ADD CONSTRAINT disciplines_family_check CHECK (family IN ('track', 'field', 'relay', 'combat'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('disciplines', function (Blueprint $table): void {
                $table->enum('family', ['track', 'field', 'relay'])->change();
            });

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE disciplines DROP CONSTRAINT IF EXISTS disciplines_family_check');
        DB::statement("ALTER TABLE disciplines ADD CONSTRAINT disciplines_family_check CHECK (family IN ('track', 'field', 'relay'))");
    }
};
