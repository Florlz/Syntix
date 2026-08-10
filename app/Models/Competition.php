<?php

namespace App\Models;

use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['event_id', 'name', 'slug', 'is_active', 'deactivation_reason', 'deactivated_at'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class, 'competition_id');
    }

    public function coverImages(): HasMany
    {
        return $this->hasMany(CompetitionCoverImage::class);
    }

    public function draftCoverImage(): HasOne
    {
        return $this->hasOne(CompetitionCoverImage::class)
            ->where('state', 'draft')
            ->latestOfMany('revision');
    }

    public function publishedCoverImage(): HasOne
    {
        return $this->hasOne(CompetitionCoverImage::class)
            ->where('state', 'published')
            ->latestOfMany('revision');
    }

    public function eventId(): ?int
    {
        return $this->event_id === null ? null : (int) $this->event_id;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deactivated_at' => 'datetime'];
    }
}
