<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'division_placement_id',
    'entry_id',
    'event_delegation_id',
    'rank',
    'placement_key',
    'championship_points',
    'participation_eligible',
    'metadata',
])]
class DivisionPlacementItem extends Model
{
    public function placement(): BelongsTo
    {
        return $this->belongsTo(DivisionPlacement::class, 'division_placement_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ScoreLedgerEntry::class);
    }

    protected function casts(): array
    {
        return [
            'championship_points' => 'decimal:4',
            'participation_eligible' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
