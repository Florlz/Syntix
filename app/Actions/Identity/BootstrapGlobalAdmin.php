<?php

namespace App\Actions\Identity;

use App\Enums\AccountState;
use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class BootstrapGlobalAdmin
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes, ?string $bootstrapContext = null): User
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $email = Str::lower(trim((string) ($attributes['email'] ?? '')));
        $password = (string) ($attributes['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException('A name, institutional email, and setup password are required.');
        }

        try {
            return DB::transaction(function () use ($name, $email, $password, $bootstrapContext): User {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT pg_advisory_xact_lock(hashtext('syntix.global_admin.bootstrap'))");
                }

                $existingAdmin = User::query()
                    ->where('is_global_admin', true)
                    ->lockForUpdate()
                    ->first();

                if ($existingAdmin !== null) {
                    if ($existingAdmin->email !== $email) {
                        throw new \DomainException('The Global Admin has already been bootstrapped.');
                    }

                    return $existingAdmin;
                }

                $user = User::query()->where('email', $email)->lockForUpdate()->first();

                if ($user === null) {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'account_state' => AccountState::Active,
                        'email_verified_at' => now(),
                        'is_global_admin' => true,
                    ]);
                } else {
                    $user->forceFill([
                        'name' => $name,
                        'password' => Hash::make($password),
                        'account_state' => AccountState::Active,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        'is_global_admin' => true,
                        'disable_reason' => null,
                        'disabled_at' => null,
                        'disabled_by' => null,
                    ])->save();
                }

                ($this->audit ?? new AuditLogger)->record(
                    null,
                    AuditAction::GlobalAdminBootstrapped,
                    $user,
                    after: [
                        'account_state' => AccountState::Active->value,
                        'is_global_admin' => true,
                    ],
                    context: array_filter(['bootstrap_context' => $bootstrapContext]),
                );

                return $user;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'users_single_global_admin')) {
                throw new \DomainException('The Global Admin has already been bootstrapped.', previous: $exception);
            }

            throw $exception;
        }
    }
}
