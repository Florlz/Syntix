<?php

namespace App\Actions\Brackets;

use App\Enums\CompetitionFormat;
use App\Enums\EventRole;
use App\Models\Division;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class GenerateDoubleEliminationBracket
{
    /**
     * Double-elimination routing is deliberately not inferred. A signed map
     * must be attached to the frozen rule version before generation is enabled.
     *
     * @param  list<int>  $drawOrder
     */
    public function handle(User $actor, Division $division, array $drawOrder): never
    {
        $division->loadMissing('competition.event', 'governingRuleVersion');
        $event = $division->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can generate a bracket.');
        }

        if ($division->governingRuleVersion?->format() !== CompetitionFormat::DoubleElimination) {
            throw new \DomainException('The Division is not configured for double elimination.');
        }

        $routingMap = $division->governingRuleVersion->scoring_configuration['double_elimination_routing_map'] ?? null;

        if (! is_array($routingMap) || ($routingMap['signed_off'] ?? false) !== true) {
            throw new \DomainException('Double-elimination generation is blocked until a signed-off routing map covers this entry count.');
        }

        throw new \DomainException('The signed-off double-elimination routing map requires a format-size generator before activation.');
    }
}
