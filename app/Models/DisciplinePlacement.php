<?php

namespace App\Models;

use App\Enums\DisciplineResultState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'discipline_id',
    'entry_id',
    'event_delegation_id',
    'rank',
    'sub_points',
    'state',
    'approved_by',
    'approved_at',
    'reason',
])]
class DisciplinePlacement extends Model
{
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    public function subPoints(): HasMany
    {
        return $this->hasMany(DivisionSubPoint::class, 'discipline_placement_id');
    }

    public function placementState(): DisciplineResultState
    {
        $state = $this->getAttribute('state');

        return $state instanceof DisciplineResultState
            ? $state
            : DisciplineResultState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'sub_points' => 'decimal:4',
            'state' => DisciplineResultState::class,
            'approved_at' => 'datetime',
        ];
    }
}
