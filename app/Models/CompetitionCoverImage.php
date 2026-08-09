<?php

namespace App\Models;

use App\Enums\PublicationState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'competition_id',
    'revision',
    'private_path',
    'public_path',
    'mime_type',
    'file_size',
    'width',
    'height',
    'alt_text',
    'state',
    'created_by',
    'published_by',
    'published_at',
    'withdrawn_by',
    'withdrawn_at',
    'withdrawal_reason',
])]
class CompetitionCoverImage extends Model
{
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function withdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'state' => PublicationState::class,
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
