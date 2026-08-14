<?php

namespace App\Models;

use App\Enums\ParticipationExceptionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'entry_id', 'participant_id', 'type', 'reason', 'recorded_by', 'recorded_at', 'legacy_eligibility_record_id'])]
class ParticipationException extends Model
{
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function entry(): BelongsTo { return $this->belongsTo(Entry::class); }
    public function participant(): BelongsTo { return $this->belongsTo(Participant::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    protected function casts(): array
    {
        return ['type' => ParticipationExceptionType::class, 'recorded_at' => 'datetime'];
    }
}
