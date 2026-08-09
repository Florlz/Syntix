<?php

namespace App\Actions\Identity;

use App\Enums\AuditAction;
use App\Enums\PlatformCapability;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GrantPlatformCapability
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(
        User $actor,
        User $target,
        PlatformCapability|string $capability,
        ?string $reason = null,
    ): PlatformCapabilityGrant {
        $capability = $capability instanceof PlatformCapability
            ? $capability
            : PlatformCapability::from($capability);

        return DB::transaction(function () use ($actor, $target, $capability, $reason): PlatformCapabilityGrant {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->hasActivePlatformCapability(PlatformCapability::EventCreator)) {
                throw new AuthorizationException('An active event creator is required for platform capability changes.');
            }

            if (! $target->isActive()) {
                throw new AuthorizationException('Disabled accounts cannot receive platform capabilities.');
            }

            $duplicate = PlatformCapabilityGrant::query()
                ->where('user_id', $target->getKey())
                ->where('capability', $capability->value)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new \DomainException('The user already has this active platform capability.');
            }

            $grant = PlatformCapabilityGrant::create([
                'user_id' => $target->getKey(),
                'capability' => $capability,
                'granted_by' => $actor->getKey(),
                'granted_at' => now(),
                'reason' => $reason,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::PlatformCapabilityGranted,
                $grant,
                after: [
                    'user_id' => $target->getKey(),
                    'capability' => $capability->value,
                    'active' => true,
                ],
                reason: $reason,
            );

            return $grant;
        });
    }
}
