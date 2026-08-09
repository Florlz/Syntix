<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDelegation;
use Illuminate\Support\Collection;

final class StandingCalculator
{
    /**
     * @return Collection<int, EventDelegation>
     */
    public function forEvent(Event $event): Collection
    {
        return $event->delegations()
            ->withSum('ledgerEntries as championship_total', 'amount')
            ->orderByDesc('championship_total')
            ->orderBy('name')
            ->get();
    }

    public function totalForDelegation(EventDelegation $delegation): string
    {
        return (string) ($delegation->ledgerEntries()->sum('amount') ?? '0');
    }
}
