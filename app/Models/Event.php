<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\EventRole;
use App\Enums\EventState;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'state',
    'created_by',
    'starts_at',
    'ends_at',
    'archived_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(EventUserRole::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(EventDelegation::class);
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScoringAssignment::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ScoreLedgerEntry::class);
    }

    public function hasActiveAdmin(): bool
    {
        return $this->userRoles()
            ->where('role', EventRole::Admin->value)
            ->whereNull('revoked_at')
            ->whereHas('user', function ($query): void {
                $query->where('account_state', AccountState::Active->value);
            })
            ->exists();
    }

    public function isArchived(): bool
    {
        return $this->eventState() === EventState::Archived;
    }

    public function eventState(): EventState
    {
        $state = $this->getAttribute('state');

        if ($state instanceof EventState) {
            return $state;
        }

        return EventState::tryFrom((string) ($state ?? EventState::Preparation->value))
            ?? EventState::Preparation;
    }

    protected function casts(): array
    {
        return [
            'state' => EventState::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
