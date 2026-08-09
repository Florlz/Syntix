<?php

namespace App\Actions\Identity;

use App\Enums\AccountState;
use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Enums\PlatformCapability;
use App\Models\Event;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionRevoker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DisableUser
{
    public function __construct(
        private readonly ?AuditLogger $audit = null,
        private readonly ?SessionRevoker $sessions = null,
    ) {}

    public function handle(User $actor, User $target, string $reason, ?Event $event = null): User
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required when disabling an account.');
        }

        return DB::transaction(function () use ($actor, $target, $reason, $event): User {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            $adminQuery = $actor->eventRoles()
                ->where('role', EventRole::Admin->value)
                ->whereNull('revoked_at');

            if ($event !== null) {
                $adminQuery->where('event_id', $event->getKey());
            }

            if (! $actor->isActive() || ! $adminQuery->exists()) {
                throw new AuthorizationException('An active event Admin is required to disable an account.');
            }

            if (! $target->isActive()) {
                throw new \DomainException('The account is already disabled.');
            }

            $creatorGrants = PlatformCapabilityGrant::query()
                ->where('capability', PlatformCapability::EventCreator->value)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();
            $creatorIds = User::query()
                ->whereIn('id', $creatorGrants->pluck('user_id'))
                ->where('account_state', AccountState::Active->value)
                ->lockForUpdate()
                ->pluck('id');

            if ($creatorIds->contains($target->getKey()) && $creatorIds->count() <= 1) {
                throw new \DomainException('The last active event creator cannot be disabled.');
            }

            $before = [
                'account_state' => AccountState::Active->value,
                'disabled_at' => null,
                'disabled_by' => null,
            ];

            $target->forceFill([
                'account_state' => AccountState::Disabled,
                'disable_reason' => $reason,
                'disabled_at' => now(),
                'disabled_by' => $actor->getKey(),
                'remember_token' => null,
            ])->save();

            $sessionsRevoked = ($this->sessions ?? new SessionRevoker)->revoke($target);
            $audit = $this->audit ?? new AuditLogger;

            $audit->record(
                $actor,
                AuditAction::UserDisabled,
                $target,
                $event,
                before: $before,
                after: [
                    'account_state' => AccountState::Disabled->value,
                    'disabled_at' => $target->disabled_at?->toIso8601String(),
                    'disabled_by' => $actor->getKey(),
                    'sessions_revoked' => $sessionsRevoked,
                ],
                reason: $reason,
            );
            $audit->record(
                $actor,
                AuditAction::UserSessionsRevoked,
                $target,
                $event,
                after: ['sessions_revoked' => $sessionsRevoked],
                reason: 'Account disabled: '.$reason,
            );

            return $target;
        });
    }
}
