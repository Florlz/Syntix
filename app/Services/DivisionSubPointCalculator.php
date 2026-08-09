<?php

namespace App\Services;

use App\Models\Division;
use Illuminate\Support\Collection;

final class DivisionSubPointCalculator
{
    /**
     * @return Collection<int, object>
     */
    public function standings(Division $division): Collection
    {
        return $division->subPoints()
            ->selectRaw('event_delegation_id, SUM(amount) as sub_point_total')
            ->groupBy('event_delegation_id')
            ->orderByDesc('sub_point_total')
            ->get();
    }
}
