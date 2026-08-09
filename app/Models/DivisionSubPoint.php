<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'competition_division_id',
    'discipline_placement_id',
    'entry_id',
    'event_delegation_id',
    'amount',
    'source_key',
    'committed_at',
])]
class DivisionSubPoint extends Model
{
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(DisciplinePlacement::class, 'discipline_placement_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'committed_at' => 'datetime',
        ];
    }
}
