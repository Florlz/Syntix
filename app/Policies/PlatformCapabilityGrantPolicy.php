<?php

namespace App\Policies;

use App\Enums\PlatformCapability;
use App\Models\PlatformCapabilityGrant;
use App\Models\User;

class PlatformCapabilityGrantPolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasActivePlatformCapability(PlatformCapability::EventCreator);
    }

    public function revoke(User $actor, PlatformCapabilityGrant $grant): bool
    {
        return $grant->isActive()
            && $actor->hasActivePlatformCapability(PlatformCapability::EventCreator);
    }
}
