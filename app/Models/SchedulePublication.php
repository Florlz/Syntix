<?php

namespace App\Models;

use App\Enums\PublicationState;
use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_id',
    'revision',
    'competition_name',
    'division_name',
    'title',
    'starts_at',
    'ends_at',
    'status',
    'venue_name',
    'venue_location',
    'state',
    'published_by',
    'published_at',
    'withdrawn_by',
    'withdrawn_at',
    'withdrawal_reason',
])]
class SchedulePublication extends Model
{
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ScheduleStatus::class,
            'state' => PublicationState::class,
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
