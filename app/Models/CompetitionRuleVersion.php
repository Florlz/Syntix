<?php

namespace App\Models;

use App\Enums\CompetitionFormat;
use App\Enums\CriterionNumberMeaning;
use App\Enums\ParticipantMode;
use App\Enums\RuleVersionState;
use App\Enums\ScoringFamily;
use Database\Factories\CompetitionRuleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_division_id',
    'placement_point_template_id',
    'version',
    'lifecycle_state',
    'is_governing',
    'scoring_family',
    'format',
    'participant_mode',
    'min_roster_size',
    'max_roster_size',
    'entries_per_delegation',
    'participant_competition_limit',
    'roster_role_limits',
    'criteria_calculation_mode',
    'verified_scorecard_total',
    'judge_aggregation_method',
    'deduction_configuration',
    'input_scale',
    'calculation_scale',
    'display_scale',
    'comparison_scale',
    'rounding_mode',
    'rounding_stage',
    'tie_breaker_configuration',
    'participation_configuration',
    'publication_configuration',
    'approval_configuration',
    'scoring_configuration',
    'source_reference',
    'source_status',
    'created_by',
    'activated_by',
    'supersedes_id',
    'activated_at',
    'frozen_at',
    'archived_at',
])]
#[Hidden(['scoring_configuration'])]
class CompetitionRuleVersion extends Model
{
    /** @use HasFactory<CompetitionRuleVersionFactory> */
    use HasFactory;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function event(): ?Event
    {
        $this->loadMissing('division.competition');

        return $this->division?->competition?->event;
    }

    public function pointTemplate(): BelongsTo
    {
        return $this->belongsTo(PlacementPointTemplate::class, 'placement_point_template_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ScoringCriterion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function lifecycleState(): RuleVersionState
    {
        $state = $this->getAttribute('lifecycle_state');

        if ($state instanceof RuleVersionState) {
            return $state;
        }

        return $state === null || $state === ''
            ? RuleVersionState::Draft
            : RuleVersionState::from((string) $state);
    }

    public function scoringFamily(): ?ScoringFamily
    {
        $family = $this->getAttribute('scoring_family');

        return $family instanceof ScoringFamily || $family === null
            ? $family
            : ScoringFamily::from((string) $family);
    }

    public function format(): ?CompetitionFormat
    {
        $format = $this->getAttribute('format');

        return $format instanceof CompetitionFormat || $format === null
            ? $format
            : CompetitionFormat::from((string) $format);
    }

    public function participantMode(): ?ParticipantMode
    {
        $mode = $this->getAttribute('participant_mode');

        return $mode instanceof ParticipantMode || $mode === null
            ? $mode
            : ParticipantMode::from((string) $mode);
    }

    public function criteriaCalculationMode(): ?CriterionNumberMeaning
    {
        $mode = $this->getAttribute('criteria_calculation_mode');

        return $mode instanceof CriterionNumberMeaning || $mode === null
            ? $mode
            : CriterionNumberMeaning::from((string) $mode);
    }

    public function isMutable(): bool
    {
        $division = $this->relationLoaded('division')
            ? $this->division
            : $this->division()->first();

        return in_array($this->lifecycleState(), [
            RuleVersionState::Draft,
            RuleVersionState::ActivatedEditable,
        ], true) && ! $division?->hasScoringStarted();
    }

    public function isFrozenOrHistorical(): bool
    {
        return in_array($this->lifecycleState(), [
            RuleVersionState::Frozen,
            RuleVersionState::Superseded,
            RuleVersionState::Archived,
        ], true);
    }

    /**
     * Return every activation blocker without guessing unresolved institutional
     * rules or normalizing source values.
     *
     * @return list<string>
     */
    public function readinessErrors(): array
    {
        $errors = [];

        foreach ([
            'scoring_family' => $this->scoringFamily(),
            'format' => $this->format(),
            'participant_mode' => $this->participantMode(),
            'placement_point_template_id' => $this->placement_point_template_id,
            'input_scale' => $this->input_scale,
            'calculation_scale' => $this->calculation_scale,
            'display_scale' => $this->display_scale,
            'rounding_mode' => $this->rounding_mode,
            'rounding_stage' => $this->rounding_stage,
            'tie_breaker_configuration' => $this->tie_breaker_configuration,
            'participation_configuration' => $this->participation_configuration,
            'publication_configuration' => $this->publication_configuration,
            'approval_configuration' => $this->approval_configuration,
        ] as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                $errors[] = "Missing required rule configuration: {$field}.";
            }
        }

        $this->loadMissing(['pointTemplate.rules', 'criteria']);

        if (! $this->pointTemplate?->is_signed_off) {
            $errors[] = 'The placement point template must be signed off before activation.';
        }

        if ($this->source_status === 'blocked') {
            $errors[] = 'The source rules are institutionally blocked and cannot be activated.';
        }

        if ($this->scoringFamily() === ScoringFamily::CriteriaBased) {
            if ($this->criteriaCalculationMode() === null) {
                $errors[] = 'Criteria-based Divisions require an explicit criterion number meaning.';
            }

            if ($this->judge_aggregation_method === null || $this->judge_aggregation_method === '') {
                $errors[] = 'Criteria-based Divisions require an explicit Judge aggregation method.';
            }

            if ($this->criteria->isEmpty()) {
                $errors[] = 'Criteria-based Divisions require at least one criterion.';
            }

            $mode = $this->criteriaCalculationMode();
            $total = 0;

            foreach ($this->criteria as $criterion) {
                if ($criterion->source_label === '') {
                    $errors[] = 'Every criterion requires a source label.';
                }

                if ($mode === CriterionNumberMeaning::PercentageWeight) {
                    if ($criterion->weight === null) {
                        $errors[] = "Criterion {$criterion->name} is missing its weight.";
                    } else {
                        $total += self::scaledDecimal((string) $criterion->weight, 4);
                    }
                }

                if ($mode === CriterionNumberMeaning::PointMaximum
                    && $criterion->maximum_points === null) {
                    $errors[] = "Criterion {$criterion->name} is missing its maximum points.";
                }
            }

            if ($mode === CriterionNumberMeaning::PercentageWeight && $total !== 1000000) {
                $errors[] = 'Criteria weights must total exactly 100 percent.';
            }

            if ($this->verified_scorecard_total === null) {
                $errors[] = 'Criteria-based Divisions require a verified scorecard total.';
            }
        }

        return array_values(array_unique($errors));
    }

    public function assertReadyForActivation(): void
    {
        $errors = $this->readinessErrors();

        if ($errors !== []) {
            throw new \DomainException(implode(' ', $errors));
        }
    }

    public function pointRuleFor(string $placementKey, bool $participationEligible = false): ?PlacementPointRule
    {
        $this->loadMissing('pointTemplate.rules');

        if ($participationEligible) {
            $participationRule = $this->pointTemplate?->rules
                ->first(fn (PlacementPointRule $rule): bool => $rule->is_participation);

            if ($participationRule !== null) {
                return $participationRule;
            }
        }

        return $this->pointTemplate?->rules
            ->first(fn (PlacementPointRule $rule): bool => $rule->placement_key === $placementKey);
    }

    private static function scaledDecimal(string $value, int $scale): int
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    protected static function booted(): void
    {
        static::updating(function (CompetitionRuleVersion $version): void {
            $originalState = RuleVersionState::tryFrom(
                (string) $version->getRawOriginal('lifecycle_state')
            );
            $newState = $version->lifecycleState();
            $dirty = array_keys($version->getDirty());
            $lifecycleOnly = [
                'lifecycle_state',
                'is_governing',
                'activated_at',
                'activated_by',
                'frozen_at',
                'archived_at',
                'supersedes_id',
                'updated_at',
            ];

            if ($originalState !== null
                && $newState !== $originalState
                && ! self::isAllowedTransition($originalState, $newState)) {
                throw new \DomainException("Invalid rule-version transition from {$originalState->value} to {$newState->value}.");
            }

            if ($originalState !== null
                && in_array($originalState, [
                    RuleVersionState::Frozen,
                    RuleVersionState::Superseded,
                    RuleVersionState::Archived,
                ], true)
                && array_diff($dirty, $lifecycleOnly) !== []) {
                throw new \DomainException('Frozen rule versions are immutable.');
            }

            if ($originalState === RuleVersionState::ActivatedEditable
                && $version->division()->first()?->hasScoringStarted()
                && array_diff($dirty, $lifecycleOnly) !== []) {
                throw new \DomainException('Rule versions become immutable when scoring starts.');
            }
        });

        static::deleting(function (CompetitionRuleVersion $version): void {
            if ($version->lifecycleState() !== RuleVersionState::Draft) {
                throw new \DomainException('Only draft rule versions can be deleted.');
            }
        });
    }

    private static function isAllowedTransition(RuleVersionState $from, RuleVersionState $to): bool
    {
        return match ($from) {
            RuleVersionState::Draft => $to === RuleVersionState::ActivatedEditable,
            RuleVersionState::ActivatedEditable => $to === RuleVersionState::Frozen
                || $to === RuleVersionState::Superseded,
            RuleVersionState::Frozen => $to === RuleVersionState::Superseded,
            RuleVersionState::Superseded => $to === RuleVersionState::Archived,
            RuleVersionState::Archived => false,
        };
    }

    protected function casts(): array
    {
        return [
            'lifecycle_state' => RuleVersionState::class,
            'is_governing' => 'boolean',
            'scoring_family' => ScoringFamily::class,
            'format' => CompetitionFormat::class,
            'participant_mode' => ParticipantMode::class,
            'criteria_calculation_mode' => CriterionNumberMeaning::class,
            'verified_scorecard_total' => 'decimal:4',
            'roster_role_limits' => 'array',
            'deduction_configuration' => 'array',
            'tie_breaker_configuration' => 'array',
            'participation_configuration' => 'array',
            'publication_configuration' => 'array',
            'approval_configuration' => 'array',
            'scoring_configuration' => 'array',
            'activated_at' => 'datetime',
            'frozen_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
