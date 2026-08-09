<?php

namespace App\Models;

use App\Enums\CompetitionFormat;
use App\Enums\TournamentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'competition_rule_version_id',
    'format',
    'state',
    'eligible_entry_count',
    'created_by',
    'draw_locked_at',
    'published_at',
])]
class Tournament extends Model
{
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CompetitionRuleVersion::class, 'competition_rule_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function drawRecords(): HasMany
    {
        return $this->hasMany(DrawRecord::class);
    }

    public function bracketVersions(): HasMany
    {
        return $this->hasMany(BracketVersion::class);
    }

    public function formatValue(): CompetitionFormat
    {
        $format = $this->getAttribute('format');

        return $format instanceof CompetitionFormat
            ? $format
            : CompetitionFormat::from((string) $format);
    }

    public function tournamentState(): TournamentState
    {
        $state = $this->getAttribute('state');

        return $state instanceof TournamentState
            ? $state
            : TournamentState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'format' => CompetitionFormat::class,
            'state' => TournamentState::class,
            'draw_locked_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
