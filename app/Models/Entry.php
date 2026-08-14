<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\ParticipantMode;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'event_delegation_id',
    'code',
    'name',
    'entry_mode',
    'status',
    'locked_at',
    'locked_by',
    'status_reason',
])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(EventDelegation::class, 'event_delegation_id');
    }

    public function rosterMembers(): HasMany
    {
        return $this->hasMany(RosterMember::class);
    }

    public function eligibilityRecords(): HasMany
    {
        return $this->hasMany(EligibilityRecord::class);
    }

    public function rosterApprovals(): HasMany { return $this->hasMany(RosterApproval::class); }
    public function participationExceptions(): HasMany { return $this->hasMany(ParticipationException::class); }

    public function disciplineEntries(): HasMany
    {
        return $this->hasMany(DisciplineEntry::class);
    }

    public function eventId(): ?int
    {
        $this->loadMissing('division.competition');

        return $this->division?->eventId();
    }

    public function entryMode(): ParticipantMode
    {
        $mode = $this->getAttribute('entry_mode');

        return $mode instanceof ParticipantMode
            ? $mode
            : ParticipantMode::from((string) $mode);
    }

    public function entryStatus(): EntryStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof EntryStatus
            ? $status
            : EntryStatus::from((string) $status);
    }

    public function isLocked(): bool
    {
        return $this->entryStatus() === EntryStatus::Locked;
    }

    protected function casts(): array
    {
        return [
            'entry_mode' => ParticipantMode::class,
            'status' => EntryStatus::class,
            'locked_at' => 'datetime',
        ];
    }
}
