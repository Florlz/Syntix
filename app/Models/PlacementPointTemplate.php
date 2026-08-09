<?php

namespace App\Models;

use Database\Factories\PlacementPointTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'code',
    'name',
    'version',
    'source_reference',
    'is_signed_off',
    'metadata',
])]
class PlacementPointTemplate extends Model
{
    /** @use HasFactory<PlacementPointTemplateFactory> */
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PlacementPointRule::class);
    }

    protected static function booted(): void
    {
        static::updating(function (PlacementPointTemplate $template): void {
            if ($template->getRawOriginal('is_signed_off') && $template->isDirty()) {
                throw new \DomainException('Signed-off placement point templates are immutable.');
            }
        });

        static::deleting(function (PlacementPointTemplate $template): void {
            if ($template->is_signed_off) {
                throw new \DomainException('Signed-off placement point templates cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_signed_off' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
