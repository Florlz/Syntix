<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contest_id', 'entry_id', 'slot', 'state'])]
class ContestEntry extends Model
{
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
