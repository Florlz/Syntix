<?php

namespace App\Models;

use App\Enums\CriterionNumberMeaning;
use Database\Factories\ScoringCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'competition_rule_version_id',
    'name',
    'source_label',
    'display_order',
    'number_meaning',
    'weight',
    'maximum_points',
    'raw_minimum',
    'raw_maximum',
    'input_scale',
    'is_required',
    'deduction_configuration',
    'source_page',
    'transcription_status',
    'reviewer',
    'approval_reference',
])]
class ScoringCriterion extends Model
{
    /** @use HasFactory<ScoringCriterionFactory> */
    use HasFactory;

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CompetitionRuleVersion::class, 'competition_rule_version_id');
    }

    protected static function booted(): void
    {
        static::saving(function (ScoringCriterion $criterion): void {
            $criterion->loadMissing('ruleVersion');

            if (! $criterion->ruleVersion?->isMutable()) {
                throw new \DomainException('Criteria cannot be changed after scoring starts.');
            }
        });

        static::deleting(function (ScoringCriterion $criterion): void {
            $criterion->loadMissing('ruleVersion');

            if (! $criterion->ruleVersion?->isMutable()) {
                throw new \DomainException('Criteria cannot be deleted after scoring starts.');
            }
        });
    }

    public function numberMeaning(): CriterionNumberMeaning
    {
        $meaning = $this->getAttribute('number_meaning');

        return $meaning instanceof CriterionNumberMeaning
            ? $meaning
            : CriterionNumberMeaning::from((string) $meaning);
    }

    protected function casts(): array
    {
        return [
            'number_meaning' => CriterionNumberMeaning::class,
            'weight' => 'decimal:4',
            'maximum_points' => 'decimal:4',
            'raw_minimum' => 'decimal:4',
            'raw_maximum' => 'decimal:4',
            'is_required' => 'boolean',
            'deduction_configuration' => 'array',
        ];
    }
}
