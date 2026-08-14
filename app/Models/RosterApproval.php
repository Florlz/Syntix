<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'entry_id', 'revision', 'players_snapshot', 'coaches_snapshot', 'limits_snapshot', 'source_context', 'approved_by', 'approved_at'])]
class RosterApproval extends Model
{
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function entry(): BelongsTo { return $this->belongsTo(Entry::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    protected function casts(): array
    {
        return ['players_snapshot' => 'array', 'coaches_snapshot' => 'array', 'limits_snapshot' => 'array', 'source_context' => 'array', 'approved_at' => 'datetime'];
    }
}
