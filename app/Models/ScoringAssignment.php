<?php

namespace App\Models;

use App\Enums\ScoringAssignmentScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'user_id',
    'scope_type',
    'competition_division_id',
    'contest_id',
    'entry_scorecard_id',
    'granted_by',
    'granted_at',
    'revoked_by',
    'revoked_at',
    'reason',
])]
class ScoringAssignment extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'competition_division_id');
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function entryScorecard(): BelongsTo
    {
        return $this->belongsTo(EntryScorecard::class, 'entry_scorecard_id');
    }

    public function target(): ?Model
    {
        return match ($this->scopeType()) {
            ScoringAssignmentScope::CompetitionDivision => $this->division,
            ScoringAssignmentScope::Contest => $this->contest,
            ScoringAssignmentScope::EntryScorecard => $this->entryScorecard,
        };
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function matches(Model $target): bool
    {
        if (! $this->isActive() || ! $target->exists) {
            return false;
        }

        $targetEventId = self::eventIdForTarget($target);

        if ($targetEventId === null || (int) $this->event_id !== $targetEventId) {
            return false;
        }

        return match ($this->scopeType()) {
            ScoringAssignmentScope::CompetitionDivision => ($target instanceof Division
                && (int) $this->competition_division_id === (int) $target->getKey())
                || ($target instanceof Contest
                    && (int) $this->competition_division_id === (int) $target->competition_division_id),
            ScoringAssignmentScope::Contest => $target instanceof Contest
                && (int) $this->contest_id === (int) $target->getKey(),
            ScoringAssignmentScope::EntryScorecard => $target instanceof EntryScorecard
                && (int) $this->entry_scorecard_id === (int) $target->getKey(),
        };
    }

    public function scopeType(): ScoringAssignmentScope
    {
        $scope = $this->getAttribute('scope_type');

        if ($scope instanceof ScoringAssignmentScope) {
            return $scope;
        }

        return ScoringAssignmentScope::from((string) $scope);
    }

    public static function eventIdForTarget(Model $target): ?int
    {
        if ($target instanceof Division) {
            return $target->eventId();
        }

        if ($target instanceof Contest || $target instanceof EntryScorecard) {
            return $target->eventId();
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'scope_type' => ScoringAssignmentScope::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
