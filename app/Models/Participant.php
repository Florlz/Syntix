<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'event_delegation_id',
    'display_name',
    'given_name',
    'family_name',
    'student_number',
    'student_number_normalized',
    'email',
    'phone',
    'private_notes',
    'is_active',
    'created_by',
])]
#[Hidden(['student_number', 'student_number_normalized', 'email', 'phone', 'private_notes'])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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

    public function eventId(): ?int
    {
        return $this->event_id === null ? null : (int) $this->event_id;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
