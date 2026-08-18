<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contest_id', 'entry_id', 'competition_rule_version_id', 'code', 'label',
    'source_reference', 'input_value', 'input_unit', 'points', 'notes',
    'recorded_by', 'recorded_at', 'voided_by', 'voided_at', 'void_reason',
])]
class ScoringAdjustment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query) => $query->whereNull('voided_at'));
    }

    public function contest(): BelongsTo { return $this->belongsTo(Contest::class); }
    public function entry(): BelongsTo { return $this->belongsTo(Entry::class); }
    public function ruleVersion(): BelongsTo { return $this->belongsTo(CompetitionRuleVersion::class, 'competition_rule_version_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }

    public function scopeWithVoided(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active');
    }

    public function isVoided(): bool { return $this->voided_at !== null; }

    protected function casts(): array
    {
        return [
            'input_value' => 'decimal:4',
            'points' => 'decimal:4',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
