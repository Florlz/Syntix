<?php

namespace App\Actions\Identity;

use App\Actions\Events\GrantEventRole;
use App\Enums\EventRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProvisionEventScorer
{
    /**
     * @param  array{name: string, email: string}  $attributes
     * @return array<string, mixed>
     */
    public function handle(
        User $actor,
        Event $event,
        array $attributes,
        EventRole $role,
    ): array {
        return DB::transaction(function () use ($actor, $event, $attributes, $role): array {
            $result = (new ProvisionUser)->handle($actor, $event, $attributes);
            $membership = (new GrantEventRole)->handle(
                $actor,
                $event,
                $result['user'],
                $role,
                'Provisioned for event scoring operations.',
            );

            return $result + compact('membership');
        });
    }
}
