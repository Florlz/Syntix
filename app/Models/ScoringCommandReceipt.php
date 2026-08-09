<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'command_uuid',
    'actor_id',
    'event_id',
    'schema_version',
    'command_type',
    'disposition',
    'envelope_hash',
    'base_revision',
    'depends_on_command_uuid',
    'canonical_envelope',
    'response',
    'resulting_revision',
    'error_code',
    'applied_at',
])]
class ScoringCommandReceipt extends Model
{
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected function casts(): array
    {
        return [
            'canonical_envelope' => 'array',
            'response' => 'array',
            'applied_at' => 'datetime',
        ];
    }
}
