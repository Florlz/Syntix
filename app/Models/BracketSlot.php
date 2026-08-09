<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bracket_node_id', 'slot_number', 'entry_id', 'source_node_id', 'source_result', 'label'])]
class BracketSlot extends Model
{
    public function node(): BelongsTo
    {
        return $this->belongsTo(BracketNode::class, 'bracket_node_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(BracketNode::class, 'source_node_id');
    }
}
