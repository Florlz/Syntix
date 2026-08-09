<?php

namespace App\Models;

use App\Enums\DisciplineFamily;
use Database\Factories\DisciplineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'code',
    'name',
    'family',
    'performance_type',
    'canonical_unit',
    'accepted_input_units',
    'sort_direction',
    'input_scale',
    'storage_scale',
    'display_scale',
    'qualification_configuration',
    'tie_breaker_configuration',
    'sub_point_configuration',
    'metadata',
    'is_active',
])]
class Discipline extends Model
{
    /** @use HasFactory<DisciplineFactory> */
    use HasFactory;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function competitionDivision(): BelongsTo
    {
        return $this->division();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DisciplineResult::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(DisciplinePlacement::class);
    }

    public function eventId(): ?int
    {
        $this->loadMissing('division.competition');

        return $this->division?->eventId();
    }

    public function familyType(): DisciplineFamily
    {
        $family = $this->getAttribute('family');

        return $family instanceof DisciplineFamily
            ? $family
            : DisciplineFamily::from((string) $family);
    }

    protected function casts(): array
    {
        return [
            'family' => DisciplineFamily::class,
            'accepted_input_units' => 'array',
            'qualification_configuration' => 'array',
            'tie_breaker_configuration' => 'array',
            'sub_point_configuration' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
