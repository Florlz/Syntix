<?php

use App\Enums\AccountState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('account_state', [
                AccountState::Active->value,
                AccountState::Disabled->value,
            ])->default(AccountState::Active->value)->index();
            $table->text('disable_reason')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['disabled_by']);
            $table->dropColumn([
                'account_state',
                'disable_reason',
                'disabled_at',
                'disabled_by',
            ]);
        });
    }
};
