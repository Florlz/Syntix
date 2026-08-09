<?php

namespace App\Models;

use App\Enums\OfficialOutcomeState;
use App\Enums\OutcomeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contest_id',
    'result_submission_id',
    'revision',
    'state',
    'outcome_type',
    'winner_entry_id',
    'payload',
    'approved_by',
    'approved_at',
    'reason',
])]
class OfficialContestOutcome extends Model
{
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResultSubmission::class, 'result_submission_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'winner_entry_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function outcomeState(): OfficialOutcomeState
    {
        $state = $this->getAttribute('state');

        return $state instanceof OfficialOutcomeState
            ? $state
            : OfficialOutcomeState::from((string) $state);
    }

    public function outcomeType(): OutcomeType
    {
        $type = $this->getAttribute('outcome_type');

        return $type instanceof OutcomeType
            ? $type
            : OutcomeType::from((string) $type);
    }

    protected function casts(): array
    {
        return [
            'state' => OfficialOutcomeState::class,
            'outcome_type' => OutcomeType::class,
            'payload' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
