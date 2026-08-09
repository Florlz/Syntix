<?php

namespace App\Actions\Identity;

use App\Models\User;

final class BootstrapEventCreator
{
    public function __construct(private readonly ?BootstrapGlobalAdmin $bootstrap = null) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes, ?string $bootstrapContext = null): User
    {
        return ($this->bootstrap ?? new BootstrapGlobalAdmin)->handle($attributes, $bootstrapContext);
    }
}
