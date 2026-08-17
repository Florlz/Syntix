<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contest_id', 'tied_entry_ids', 'authorized_order', 'comparison_total', 'reason', 'reference', 'resolved_by', 'resolved_at'])]
class JudgedTieResolution extends Model
{
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return [
            'tied_entry_ids' => 'array',
            'authorized_order' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
