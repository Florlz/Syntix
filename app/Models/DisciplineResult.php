<?php

namespace App\Models;

use App\Enums\DisciplineResultState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'discipline_id',
    'contest_id',
    'entry_id',
    'performance_value',
    'unit',
    'qualification_status',
    'state',
    'revision',
    'payload',
    'recorded_by',
    'approved_by',
    'submitted_at',
    'approved_at',
])]
class DisciplineResult extends Model
{
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function resultState(): DisciplineResultState
    {
        $state = $this->getAttribute('state');

        return $state instanceof DisciplineResultState
            ? $state
            : DisciplineResultState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'performance_value' => 'decimal:6',
            'state' => DisciplineResultState::class,
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
