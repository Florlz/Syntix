<?php

namespace App\Actions\Events;

use App\Enums\AuditAction;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CreateEventDelegation
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  array{name?: string, abbreviation?: string|null, color?: string|null}  $attributes
     */
    public function handle(
        User $actor,
        Event $event,
        OrganizationalUnit $organizationalUnit,
        array $attributes = [],
    ): EventDelegation {
        return DB::transaction(function () use ($actor, $event, $organizationalUnit, $attributes): EventDelegation {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $event = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $organizationalUnit = OrganizationalUnit::query()
                ->whereKey($organizationalUnit->getKey())
                ->firstOrFail();

            if (! $actor->hasAdminAccess($event)) {
                throw new AuthorizationException('The active Global Admin is required to create a delegation.');
            }

            if ($event->isArchived() || ! $organizationalUnit->is_active) {
                throw new \DomainException('Delegations cannot be added to archived events or inactive units.');
            }

            $delegation = EventDelegation::create([
                'event_id' => $event->getKey(),
                'organizational_unit_id' => $organizationalUnit->getKey(),
                'name' => trim((string) ($attributes['name'] ?? $organizationalUnit->name)),
                'abbreviation' => $attributes['abbreviation'] ?? $organizationalUnit->abbreviation,
                'color' => $attributes['color'] ?? null,
                'is_active' => true,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::EventDelegationCreated,
                $delegation,
                $event,
                after: [
                    'event_id' => $event->getKey(),
                    'organizational_unit_id' => $organizationalUnit->getKey(),
                    'name' => $delegation->name,
                    'abbreviation' => $delegation->abbreviation,
                    'color' => $delegation->color,
                ],
            );

            return $delegation;
        });
    }
}
