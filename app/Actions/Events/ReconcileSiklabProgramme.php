<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;

final class ReconcileSiklabProgramme
{
    public function __construct(private readonly ApplySiklab2025Programme $apply) {}

    public function handle(User $actor, Event $event): Event
    {
        return $this->apply->handle($actor, $event);
    }
}
