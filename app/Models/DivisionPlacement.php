<?php

namespace App\Models;

use App\Enums\DivisionPlacementState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'competition_rule_version_id',
    'revision',
    'state',
    'evidence',
    'submitted_by',
    'approved_by',
    'submitted_at',
    'approved_at',
    'reason',
])]
class DivisionPlacement extends Model
{
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CompetitionRuleVersion::class, 'competition_rule_version_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DivisionPlacementItem::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ScoreLedgerEntry::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function placementState(): DivisionPlacementState
    {
        $state = $this->getAttribute('state');

        return $state instanceof DivisionPlacementState
            ? $state
            : DivisionPlacementState::from((string) $state);
    }

    public function eventId(): ?int
    {
        return $this->division?->eventId();
    }

    protected function casts(): array
    {
        return [
            'state' => DivisionPlacementState::class,
            'evidence' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
