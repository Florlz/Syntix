<?php

namespace App\Models;

use App\Enums\AdvancementOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bracket_node_id', 'outcome', 'target_slot_id', 'display_order'])]
class AdvancementRule extends Model
{
    public function node(): BelongsTo
    {
        return $this->belongsTo(BracketNode::class, 'bracket_node_id');
    }

    public function targetSlot(): BelongsTo
    {
        return $this->belongsTo(BracketSlot::class, 'target_slot_id');
    }

    public function outcomeType(): AdvancementOutcome
    {
        $outcome = $this->getAttribute('outcome');

        return $outcome instanceof AdvancementOutcome
            ? $outcome
            : AdvancementOutcome::from((string) $outcome);
    }

    protected function casts(): array
    {
        return ['outcome' => AdvancementOutcome::class];
    }
}
