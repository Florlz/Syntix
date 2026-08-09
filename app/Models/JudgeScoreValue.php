<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'judge_scorecard_id',
    'scoring_criterion_id',
    'raw_value',
    'deduction',
    'net_value',
    'weighted_value',
    'notes',
])]
class JudgeScoreValue extends Model
{
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(EntryScorecard::class, 'judge_scorecard_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ScoringCriterion::class, 'scoring_criterion_id');
    }

    protected function casts(): array
    {
        return [
            'raw_value' => 'decimal:4',
            'deduction' => 'decimal:4',
            'net_value' => 'decimal:4',
            'weighted_value' => 'decimal:4',
        ];
    }
}
