<?php

namespace App\Models;

use App\Enums\BracketVersionState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tournament_id',
    'version',
    'state',
    'generation_algorithm_version',
    'draw_order',
    'generation_inputs',
    'created_by',
    'published_at',
])]
class BracketVersion extends Model
{
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(BracketNode::class);
    }

    public function versionState(): BracketVersionState
    {
        $state = $this->getAttribute('state');

        return $state instanceof BracketVersionState
            ? $state
            : BracketVersionState::from((string) $state);
    }

    protected function casts(): array
    {
        return [
            'state' => BracketVersionState::class,
            'draw_order' => 'array',
            'generation_inputs' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
