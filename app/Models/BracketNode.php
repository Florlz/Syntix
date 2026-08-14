<?php

namespace App\Models;

use App\Enums\BracketNodeType;
use App\Enums\BracketNodeState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bracket_version_id',
    'node_key',
    'node_type',
    'round_number',
    'sequence',
    'state',
    'contest_id',
    'metadata',
])]
class BracketNode extends Model
{
    public function bracketVersion(): BelongsTo
    {
        return $this->belongsTo(BracketVersion::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class);
    }

    public function advancementRules(): HasMany
    {
        return $this->hasMany(AdvancementRule::class);
    }

    public function nodeType(): BracketNodeType
    {
        $type = $this->getAttribute('node_type');

        return $type instanceof BracketNodeType
            ? $type
            : BracketNodeType::from((string) $type);
    }

    public function nodeState(): BracketNodeState
    {
        $state = $this->getAttribute('state');

        return $state instanceof BracketNodeState
            ? $state
            : BracketNodeState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'node_type' => BracketNodeType::class,
            'state' => BracketNodeState::class,
            'metadata' => 'array',
        ];
    }
}
