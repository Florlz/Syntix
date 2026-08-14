<?php

namespace App\Models;

use App\Enums\CompetitionFormat;
use App\Enums\TournamentFormat;
use App\Enums\TournamentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'discipline_id',
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
    /**
     * Accept the legacy CompetitionFormat enum at write boundaries while the
     * persisted tournament draw model exposes its bounded TournamentFormat
     * cast. The shared values are intentionally identical; unsupported
     * competition-only formats still fail through the native enum cast.
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'format' && $value instanceof CompetitionFormat) {
            $value = TournamentFormat::tryFrom($value->value) ?? $value;
        }

        return parent::setAttribute($key, $value);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
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

    public function formatValue(): TournamentFormat
    {
        $format = $this->getAttribute('format');

        return $format instanceof TournamentFormat
            ? $format
            : TournamentFormat::from((string) $format);
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
            'format' => TournamentFormat::class,
            'state' => TournamentState::class,
            'draw_locked_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
