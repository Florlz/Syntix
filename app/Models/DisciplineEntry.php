<?php

namespace App\Models;

use App\Enums\DisciplineEntryState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'discipline_id',
    'entry_id',
    'event_delegation_id',
    'state',
    'locked_at',
    'locked_by',
    'status_reason',
])]
class DisciplineEntry extends Model
{
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DisciplineEntryMember::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function entryState(): DisciplineEntryState
    {
        $state = $this->getAttribute('state');

        return $state instanceof DisciplineEntryState
            ? $state
            : DisciplineEntryState::from((string) $state);
    }

    /** Keep the legacy public `state` string readable while exposing the
     * bounded enum through entryState() and the database write cast. */
    protected function state(): Attribute
    {
        return Attribute::make(
            get: static fn ($value): ?string => $value instanceof DisciplineEntryState ? $value->value : ($value === null ? null : (string) $value),
            set: static fn ($value): string => $value instanceof DisciplineEntryState ? $value->value : (string) $value,
        );
    }

    public function isLocked(): bool
    {
        return $this->entryState() === DisciplineEntryState::Locked;
    }

    protected function casts(): array
    {
        return [
            'state' => DisciplineEntryState::class,
            'locked_at' => 'datetime',
        ];
    }
}
