<?php

namespace App\Actions\Events;

use App\Enums\AuditAction;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RevokeEventRole
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(EventUserRole $membership, User $actor, string $reason): EventUserRole
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required when revoking an event role.');
        }

        return DB::transaction(function () use ($membership, $actor, $reason): EventUserRole {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $membership = EventUserRole::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            $event = Event::query()->whereKey($membership->event_id)->lockForUpdate()->firstOrFail();

            if (! $actor->hasAdminAccess($event)) {
                throw new AuthorizationException('The active Global Admin is required to revoke an Event Role.');
            }

            if (! $membership->isActive()) {
                throw new \DomainException('The event role is already revoked.');
            }

            $before = [
                'event_id' => $event->getKey(),
                'user_id' => $membership->user_id,
                'role' => $membership->role->value,
                'active' => true,
            ];

            $membership->forceFill([
                'revoked_by' => $actor->getKey(),
                'revoked_at' => now(),
                'reason' => $reason,
            ])->save();

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::EventRoleRevoked,
                $membership,
                $event,
                before: $before,
                after: [
                    'event_id' => $event->getKey(),
                    'user_id' => $membership->user_id,
                    'role' => $membership->role->value,
                    'active' => false,
                ],
                reason: $reason,
            );

            return $membership;
        });
    }
}
