<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'event_delegation_id',
    'division_placement_id',
    'division_placement_item_id',
    'entry_type',
    'amount',
    'source_key',
    'source_revision',
    'created_by',
    'committed_at',
    'reason',
    'metadata',
])]
class ScoreLedgerEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \DomainException('Score Ledger Entries are append-only.');
        });

        static::deleting(function (): void {
            throw new \DomainException('Score Ledger Entries cannot be deleted.');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(DivisionPlacement::class, 'division_placement_id');
    }

    public function placementItem(): BelongsTo
    {
        return $this->belongsTo(DivisionPlacementItem::class, 'division_placement_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entryType(): LedgerEntryType
    {
        $type = $this->getAttribute('entry_type');

        return $type instanceof LedgerEntryType
            ? $type
            : LedgerEntryType::from((string) $type);
    }

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'amount' => 'decimal:4',
            'committed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
