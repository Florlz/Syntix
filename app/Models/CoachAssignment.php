<?php

namespace App\Models;

use App\Enums\CoachAssignmentScope;
use App\Enums\CoachType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'event_delegation_id', 'participant_id', 'coach_type', 'title', 'scope_type', 'scope_key', 'is_active', 'notes', 'created_by', 'deactivated_at'])]
class CoachAssignment extends Model
{
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function delegation(): BelongsTo { return $this->belongsTo(EventDelegation::class, 'event_delegation_id'); }
    public function participant(): BelongsTo { return $this->belongsTo(Participant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    protected function casts(): array
    {
        return ['coach_type' => CoachType::class, 'scope_type' => CoachAssignmentScope::class, 'is_active' => 'boolean', 'deactivated_at' => 'datetime'];
    }
}
