<?php

namespace App\Models;

use App\Enums\ScorecardState;
use Database\Factories\EntryScorecardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contest_id',
    'entry_id',
    'judge_id',
    'competition_rule_version_id',
    'entry_reference',
    'state',
    'revision',
    'calculated_total',
    'payload',
    'submitted_at',
    'approved_at',
    'rejection_reason',
])]
class EntryScorecard extends Model
{
    /** @use HasFactory<EntryScorecardFactory> */
    use HasFactory;

    protected $table = 'judge_scorecards';

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CompetitionRuleVersion::class, 'competition_rule_version_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(JudgeScoreValue::class, 'judge_scorecard_id');
    }

    public function scorecardState(): ScorecardState
    {
        $state = $this->getAttribute('state');

        return $state instanceof ScorecardState
            ? $state
            : ScorecardState::from((string) ($state ?? ScorecardState::Draft->value));
    }

    public function eventId(): ?int
    {
        $this->loadMissing('contest.division.competition');

        return $this->contest?->division?->competition?->event_id
            ? (int) $this->contest->division->competition->event_id
            : null;
    }

    protected function casts(): array
    {
        return [
            'state' => ScorecardState::class,
            'calculated_total' => 'decimal:4',
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
