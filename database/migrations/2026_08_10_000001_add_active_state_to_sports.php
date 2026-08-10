<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->text('deactivation_reason')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });

        Schema::table('competition_divisions', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->text('deactivation_reason')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competition_divisions', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'deactivation_reason', 'deactivated_at']);
        });
        Schema::table('competitions', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'deactivation_reason', 'deactivated_at']);
        });
    }
};
