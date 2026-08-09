<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\DisciplineResultState;
use App\Enums\EventRole;
use App\Models\Discipline;
use App\Models\DisciplinePlacement;
use App\Models\DivisionSubPoint;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ApproveDisciplinePlacements
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<array{entry_id: int, rank: int}>  $items
     */
    public function handle(User $actor, Discipline $discipline, array $items): array
    {
        $discipline->loadMissing('division.competition.event');
        $event = $discipline->division?->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can approve discipline placements.');
        }

        return DB::transaction(function () use ($actor, $discipline, $items, $event): array {
            $discipline = Discipline::query()
                ->with('division.competition')
                ->whereKey($discipline->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $mapping = $discipline->sub_point_configuration;

            if (! is_array($mapping) || $mapping === []) {
                throw new \DomainException('The discipline has no signed-off sub-point mapping.');
            }

            if ($items === []) {
                throw new \InvalidArgumentException('At least one discipline placement is required.');
            }

            if ($discipline->placements()->where('state', DisciplineResultState::Approved->value)->exists()) {
                throw new \DomainException('The discipline already has approved placements.');
            }

            $placements = [];
            foreach ($items as $item) {
                $entry = $discipline->division->entries()
                    ->with('delegation')
                    ->whereKey((int) ($item['entry_id'] ?? 0))
                    ->first();
                $rank = (int) ($item['rank'] ?? 0);

                if ($entry === null || $rank < 1) {
                    throw new \DomainException('Every discipline placement must reference a valid Division entry and rank.');
                }

                if (! $discipline->results()
                    ->where('entry_id', $entry->getKey())
                    ->where('state', DisciplineResultState::Approved->value)
                    ->exists()) {
                    throw new \DomainException('Every discipline placement requires an approved discipline result.');
                }

                $points = $mapping[(string) $rank] ?? $mapping[$rank] ?? null;

                if ($points === null && $rank > 3) {
                    $points = $mapping['participation'] ?? null;
                }

                if ($points === null) {
                    throw new \DomainException("No sub-point mapping exists for discipline rank {$rank}.");
                }

                $placements[] = DisciplinePlacement::create([
                    'discipline_id' => $discipline->getKey(),
                    'entry_id' => $entry->getKey(),
                    'event_delegation_id' => $entry->event_delegation_id,
                    'rank' => $rank,
                    'sub_points' => $points,
                    'state' => DisciplineResultState::Approved,
                    'approved_by' => $actor->getKey(),
                    'approved_at' => now(),
                ]);
            }

            foreach ($placements as $placement) {
                $subPoint = DivisionSubPoint::create([
                    'competition_division_id' => $discipline->competition_division_id,
                    'discipline_placement_id' => $placement->getKey(),
                    'entry_id' => $placement->entry_id,
                    'event_delegation_id' => $placement->event_delegation_id,
                    'amount' => $placement->sub_points,
                    'source_key' => "discipline-placement:{$placement->getKey()}",
                    'committed_at' => now(),
                ]);

                ($this->audit ?? new AuditLogger)->record(
                    $actor,
                    AuditAction::DivisionSubPointsCommitted,
                    $subPoint,
                    event: $event,
                    after: [
                        'amount' => (string) $placement->sub_points,
                        'discipline_placement_id' => $placement->getKey(),
                    ],
                );
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::DisciplinePlacementApproved,
                $discipline,
                event: $event,
                after: [
                    'placement_count' => count($placements),
                    'championship_ledger_created' => false,
                ],
            );

            return $placements;
        });
    }
}
