<?php

namespace App\Actions\Identity;

use App\Enums\PlatformCapability;
use App\Models\User;

final class GrantPlatformCapability
{
    public function handle(
        User $actor,
        User $target,
        PlatformCapability|string $capability,
        ?string $reason = null,
    ): never {
        $capability = $capability instanceof PlatformCapability
            ? $capability
            : PlatformCapability::from($capability);

        throw new \DomainException("The {$capability->value} capability is retired; the sole Global Admin owns platform administration.");
    }
}
