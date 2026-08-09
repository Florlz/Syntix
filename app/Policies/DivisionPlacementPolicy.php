<?php

namespace App\Policies;

use App\Enums\DivisionPlacementState;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\User;

class DivisionPlacementPolicy
{
    public function view(User $actor, DivisionPlacement $placement): bool
    {
        $placement->loadMissing('division.competition.event');
        $event = $placement->division?->competition?->event;

        return $event !== null
            && $actor->isActive()
            && $actor->hasAdminAccess($event);
    }

    public function submit(User $actor, Division $division): bool
    {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        return $event !== null && $actor->hasAdminAccess($event);
    }

    public function approve(User $actor, DivisionPlacement $placement): bool
    {
        $placement->loadMissing('division.competition.event');
        $event = $placement->division?->competition?->event;

        return $event !== null
            && $placement->placementState() === DivisionPlacementState::Submitted
            && $actor->hasAdminAccess($event);
    }
}
