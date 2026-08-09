<?php

namespace App\Actions\Events;

use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class GrantEventRole
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(
        User $actor,
        Event $event,
        User $target,
        EventRole|string $role,
        ?string $reason = null,
    ): EventUserRole {
        $role = $role instanceof EventRole ? $role : EventRole::from($role);

        if ($role === EventRole::Admin) {
            throw new \DomainException('Event Admin is retired; grant Judge or Tabulator instead.');
        }

        return DB::transaction(function () use ($actor, $event, $target, $role, $reason): EventUserRole {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $event = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->isActive() || $event->isArchived() || ! $target->isActive()) {
                throw new AuthorizationException('Active users and non-archived events are required for role grants.');
            }

            if (! $actor->hasAdminAccess($event)) {
                throw new AuthorizationException('The active Global Admin is required to grant Event Roles.');
            }

            $duplicate = EventUserRole::query()
                ->where('event_id', $event->getKey())
                ->where('user_id', $target->getKey())
                ->where('role', $role->value)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new \DomainException('The user already has this active event role.');
            }

            $membership = EventUserRole::create([
                'event_id' => $event->getKey(),
                'user_id' => $target->getKey(),
                'role' => $role,
                'granted_by' => $actor->getKey(),
                'granted_at' => now(),
                'reason' => $reason,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::EventRoleGranted,
                $membership,
                $event,
                after: [
                    'event_id' => $event->getKey(),
                    'user_id' => $target->getKey(),
                    'role' => $role->value,
                    'active' => true,
                ],
                reason: $reason,
            );

            return $membership;
        });
    }
}
