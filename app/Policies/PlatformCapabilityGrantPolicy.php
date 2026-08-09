<?php

namespace App\Policies;

use App\Models\PlatformCapabilityGrant;
use App\Models\User;

class PlatformCapabilityGrantPolicy
{
    public function create(User $actor): bool
    {
        return false;
    }

    public function revoke(User $actor, PlatformCapabilityGrant $grant): bool
    {
        return $grant->isActive() && $actor->isGlobalAdmin();
    }
}
