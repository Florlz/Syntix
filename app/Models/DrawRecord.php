<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tournament_id',
    'command_uuid',
    'draw_order',
    'random_seed',
    'algorithm_version',
    'source',
    'confirmed_by',
    'confirmed_at',
])]
#[Hidden(['random_seed'])]
class DrawRecord extends Model
{
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected function casts(): array
    {
        return [
            'draw_order' => 'array',
            'random_seed' => 'encrypted',
            'confirmed_at' => 'datetime',
        ];
    }
}
