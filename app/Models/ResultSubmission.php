<?php

namespace App\Models;

use App\Enums\ResultSubmissionState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contest_id',
    'submitted_by',
    'state',
    'contest_revision',
    'payload',
    'rejection_reason',
    'submitted_at',
    'approved_at',
])]
class ResultSubmission extends Model
{
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function officialOutcomes(): HasMany
    {
        return $this->hasMany(OfficialContestOutcome::class);
    }

    public function submissionState(): ResultSubmissionState
    {
        $state = $this->getAttribute('state');

        return $state instanceof ResultSubmissionState
            ? $state
            : ResultSubmissionState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'state' => ResultSubmissionState::class,
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
