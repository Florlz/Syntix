<?php

namespace App\Models;

use Database\Factories\PlacementPointRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'placement_point_template_id',
    'placement_key',
    'points',
    'is_participation',
    'eligibility_conditions',
    'display_order',
])]
class PlacementPointRule extends Model
{
    /** @use HasFactory<PlacementPointRuleFactory> */
    use HasFactory;

    public function template(): BelongsTo
    {
        return $this->belongsTo(PlacementPointTemplate::class, 'placement_point_template_id');
    }

    protected static function booted(): void
    {
        static::saving(function (PlacementPointRule $rule): void {
            $rule->loadMissing('template');

            if ($rule->template?->is_signed_off) {
                throw new \DomainException('Signed-off placement point templates are immutable.');
            }
        });

        static::deleting(function (PlacementPointRule $rule): void {
            $rule->loadMissing('template');

            if ($rule->template?->is_signed_off) {
                throw new \DomainException('Signed-off placement point templates are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'points' => 'decimal:4',
            'is_participation' => 'boolean',
            'eligibility_conditions' => 'array',
        ];
    }
}
