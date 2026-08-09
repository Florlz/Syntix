<?php

namespace App\Models;

use App\Enums\EligibilityStatus;
use Database\Factories\EligibilityRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'entry_id',
    'participant_id',
    'status',
    'reason',
    'checked_by',
    'checked_at',
])]
class EligibilityRecord extends Model
{
    /** @use HasFactory<EligibilityRecordFactory> */
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function eligibilityStatus(): EligibilityStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof EligibilityStatus
            ? $status
            : EligibilityStatus::from((string) $status);
    }

    protected function casts(): array
    {
        return [
            'status' => EligibilityStatus::class,
            'checked_at' => 'datetime',
        ];
    }
}
