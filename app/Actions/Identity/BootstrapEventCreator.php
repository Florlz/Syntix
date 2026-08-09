<?php

namespace App\Actions\Identity;

use App\Enums\AccountState;
use App\Enums\AuditAction;
use App\Enums\PlatformCapability;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class BootstrapEventCreator
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * Create the first event creator without a shipped or generated default
     * password. The caller must provide the secure setup secret explicitly.
     *
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

        return DB::transaction(function () use ($name, $email, $password, $bootstrapContext): User {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT pg_advisory_xact_lock(hashtext('syntix.event_creator.bootstrap'))");
            }

            $alreadyBootstrapped = PlatformCapabilityGrant::query()
                ->where('capability', PlatformCapability::EventCreator->value)
                ->whereNull('revoked_at')
                ->whereHas('user', function ($query): void {
                    $query->where('account_state', AccountState::Active->value);
                })
                ->exists();

            if ($alreadyBootstrapped) {
                throw new \DomainException('The initial event creator has already been bootstrapped.');
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'account_state' => AccountState::Active,
            ]);

            $grant = PlatformCapabilityGrant::create([
                'user_id' => $user->getKey(),
                'capability' => PlatformCapability::EventCreator,
                'granted_at' => now(),
                'reason' => 'Initial deployment bootstrap',
            ]);

            $audit = $this->audit ?? new AuditLogger;
            $audit->record(
                null,
                AuditAction::EventCreatorBootstrapped,
                $user,
                after: [
                    'account_state' => AccountState::Active->value,
                    'platform_capability' => PlatformCapability::EventCreator->value,
                ],
                context: array_filter(['bootstrap_context' => $bootstrapContext]),
            );
            $audit->record(
                null,
                AuditAction::PlatformCapabilityGranted,
                $grant,
                after: [
                    'user_id' => $user->getKey(),
                    'capability' => PlatformCapability::EventCreator->value,
                    'active' => true,
                ],
                context: array_filter(['bootstrap_context' => $bootstrapContext]),
            );

            return $user;
        });
    }
}
