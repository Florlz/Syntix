<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['competition_id', 'name', 'slug', 'scoring_started_at'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    protected $table = 'competition_divisions';

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function contests(): HasMany
    {
        return $this->hasMany(Contest::class, 'competition_division_id');
    }

    public function disciplines(): HasMany
    {
        return $this->hasMany(Discipline::class, 'competition_division_id');
    }

    public function ruleVersions(): HasMany
    {
        return $this->hasMany(CompetitionRuleVersion::class, 'competition_division_id');
    }

    public function governingRuleVersion(): HasOne
    {
        return $this->hasOne(CompetitionRuleVersion::class, 'competition_division_id')
            ->where('is_governing', true);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'competition_division_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'competition_division_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(DivisionPlacement::class, 'competition_division_id');
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'competition_division_id');
    }

    public function subPoints(): HasMany
    {
        return $this->hasMany(DivisionSubPoint::class, 'competition_division_id');
    }

    public function hasScoringStarted(): bool
    {
        return $this->getAttribute('scoring_started_at') !== null;
    }

    public function configurationIsMutable(): bool
    {
        return ! $this->hasScoringStarted();
    }

    public function eventId(): ?int
    {
        $this->loadMissing('competition');

        return $this->competition?->event_id
            ? (int) $this->competition->event_id
            : null;
    }

    protected function casts(): array
    {
        return [
            'scoring_started_at' => 'datetime',
        ];
    }
}
