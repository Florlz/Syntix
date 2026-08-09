<?php

namespace App\Actions\Brackets;

use App\Enums\AuditAction;
use App\Enums\BracketVersionState;
use App\Enums\EventRole;
use App\Enums\TournamentState;
use App\Models\BracketVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class PublishBracket
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, BracketVersion $bracket): BracketVersion
    {
        $bracket->loadMissing('tournament.division.competition.event');
        $event = $bracket->tournament?->division?->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can publish a bracket.');
        }

        return DB::transaction(function () use ($actor, $bracket, $event): BracketVersion {
            $bracket = BracketVersion::query()->whereKey($bracket->getKey())->lockForUpdate()->firstOrFail();

            if ($bracket->versionState() !== BracketVersionState::Preview) {
                throw new \DomainException('Only a bracket preview can be published.');
            }

            $bracket->update([
                'state' => BracketVersionState::Published,
                'published_at' => now(),
            ]);
            $bracket->tournament()->update([
                'state' => TournamentState::Published,
                'published_at' => now(),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::BracketPublished,
                $bracket,
                event: $event,
                after: ['state' => BracketVersionState::Published->value],
            );

            return $bracket->fresh();
        });
    }
}
