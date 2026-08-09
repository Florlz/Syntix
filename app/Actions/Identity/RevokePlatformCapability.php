<?php

namespace App\Actions\Identity;

use App\Enums\AccountState;
use App\Enums\AuditAction;
use App\Enums\PlatformCapability;
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

            if (! $actor->hasActivePlatformCapability(PlatformCapability::EventCreator)) {
                throw new AuthorizationException('An active event creator is required for platform capability changes.');
            }

            if (! $grant->isActive()) {
                throw new \DomainException('The platform capability is already revoked.');
            }

            if ($grant->capability === PlatformCapability::EventCreator) {
                $activeCreatorGrants = PlatformCapabilityGrant::query()
                    ->where('capability', PlatformCapability::EventCreator->value)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->get();

                $activeCreatorIds = User::query()
                    ->whereIn('id', $activeCreatorGrants->pluck('user_id'))
                    ->where('account_state', AccountState::Active->value)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($activeCreatorIds->count() <= 1) {
                    throw new \DomainException('The last active event creator cannot be revoked.');
                }
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
