<?php

namespace App\Actions\Identity;

use App\Enums\AuditAction;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RevokePlatformCapability
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(
        User $actor,
        PlatformCapabilityGrant $grant,
        ?string $reason = null,
    ): PlatformCapabilityGrant {
        if (trim((string) $reason) === '') {
            throw new \InvalidArgumentException('A reason is required when revoking a platform capability.');
        }

        return DB::transaction(function () use ($actor, $grant, $reason): PlatformCapabilityGrant {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $grant = PlatformCapabilityGrant::query()->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($grant->user_id)->lockForUpdate()->firstOrFail();

            if (! $actor->isGlobalAdmin()) {
                throw new AuthorizationException('Only the active Global Admin can revoke legacy platform capabilities.');
            }

            if (! $grant->isActive()) {
                throw new \DomainException('The platform capability is already revoked.');
            }

            $before = [
                'user_id' => $target->getKey(),
                'capability' => $grant->capability->value,
                'active' => true,
            ];

            $grant->forceFill([
                'revoked_by' => $actor->getKey(),
                'revoked_at' => now(),
                'reason' => $reason,
            ])->save();

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::PlatformCapabilityRevoked,
                $grant,
                before: $before,
                after: [
                    'user_id' => $target->getKey(),
                    'capability' => $grant->capability->value,
                    'active' => false,
                ],
                reason: $reason,
            );

            return $grant;
        });
    }
}
