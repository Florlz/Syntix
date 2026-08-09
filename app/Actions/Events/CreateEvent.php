<?php

namespace App\Actions\Events;

use App\Enums\AuditAction;
use App\Enums\EventState;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateEvent
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  array{name: string, slug?: string, starts_at?: mixed, ends_at?: mixed}  $attributes
     */
    public function handle(User $actor, array $attributes): Event
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('An event name is required.');
        }

        return DB::transaction(function () use ($actor, $attributes, $name): Event {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->isGlobalAdmin()) {
                throw new AuthorizationException('Only the active Global Admin can create an event.');
            }

            $slug = Str::slug((string) ($attributes['slug'] ?? $name));

            if ($slug === '') {
                throw new \InvalidArgumentException('An event slug cannot be empty.');
            }

            $event = Event::create([
                'name' => $name,
                'slug' => $slug,
                'state' => EventState::Preparation,
                'created_by' => $actor->getKey(),
                'starts_at' => $attributes['starts_at'] ?? null,
                'ends_at' => $attributes['ends_at'] ?? null,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::EventCreated,
                $event,
                $event,
                after: [
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'state' => EventState::Preparation->value,
                    'created_by' => $actor->getKey(),
                ],
            );

            return $event;
        });
    }
}
