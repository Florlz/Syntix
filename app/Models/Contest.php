<?php

namespace App\Models;

use App\Enums\ContestState;
use Database\Factories\ContestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'discipline_id',
    'competition_rule_version_id',
    'name',
    'state',
    'revision',
    'live_payload',
    'result_payload',
    'started_at',
    'completed_at',
    'completed_by',
    'cancelled_at',
    'cancel_reason',
])]
class Contest extends Model
{
    /** @use HasFactory<ContestFactory> */
    use HasFactory;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(EntryScorecard::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function resultSubmissions(): HasMany
    {
        return $this->hasMany(ResultSubmission::class);
    }

    public function officialOutcomes(): HasMany
    {
        return $this->hasMany(OfficialContestOutcome::class);
    }

    public function currentOfficialOutcome(): ?OfficialContestOutcome
    {
        return $this->officialOutcomes()
            ->where('state', 'approved')
            ->latest('revision')
            ->first();
    }

    public function eventId(): ?int
    {
        $this->loadMissing('division.competition');

        return $this->division?->competition?->event_id
            ? (int) $this->division->competition->event_id
            : null;
    }

    protected function casts(): array
    {
        return [
            'state' => ContestState::class,
            'live_payload' => 'array',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
