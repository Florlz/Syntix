<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'discipline_entry_id',
    'participant_id',
    'is_starter',
    'is_active',
    'notes',
])]
class DisciplineEntryMember extends Model
{
    public function disciplineEntry(): BelongsTo
    {
        return $this->belongsTo(DisciplineEntry::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    protected function casts(): array
    {
        return [
            'is_starter' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
