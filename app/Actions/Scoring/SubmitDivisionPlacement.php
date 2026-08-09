<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\DivisionPlacementState;
use App\Enums\EventRole;
use App\Enums\RuleVersionState;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SubmitDivisionPlacement
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  list<array{entry_id: int, rank: int, placement_key: string, participation_eligible?: bool}>  $items
     * @param  array<string, mixed>  $evidence
     */
    public function handle(User $actor, Division $division, array $items, array $evidence = []): DivisionPlacement
    {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        if ($event === null || ! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can submit a Division Placement.');
        }

        if ($items === []) {
            throw new \InvalidArgumentException('A Division Placement requires at least one placed entry.');
        }

        return DB::transaction(function () use ($actor, $division, $items, $evidence, $event): DivisionPlacement {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            $version = $division->ruleVersions()
                ->where('is_governing', true)
                ->lockForUpdate()
                ->first();

            if ($version === null || $version->lifecycleState() !== RuleVersionState::Frozen) {
                throw new \DomainException('A final Division Placement requires a frozen governing rule version.');
            }

            if ($division->placements()->where('state', DivisionPlacementState::Approved->value)->exists()) {
                throw new \DomainException('The Division already has an approved final placement.');
            }

            $ranks = [];
            $entryIds = [];
            foreach ($items as $item) {
                $rank = (int) ($item['rank'] ?? 0);
                $entryId = (int) ($item['entry_id'] ?? 0);
                $placementKey = trim((string) ($item['placement_key'] ?? ''));
                $participation = (bool) ($item['participation_eligible'] ?? false);

                if ($rank < 1 || $placementKey === '' || in_array($rank, $ranks, true) || in_array($entryId, $entryIds, true)) {
                    throw new \InvalidArgumentException('Placement ranks, entries, and keys must be unique and valid.');
                }

                $entry = $division->entries()->with('delegation')->whereKey($entryId)->first();

                if ($entry === null || $entry->delegation === null) {
                    throw new \DomainException('Every placement entry must belong to the Division and its event delegation.');
                }

                if ((int) $entry->delegation->event_id !== (int) $event->getKey()) {
                    throw new \DomainException('Placement entries must belong to the same Event.');
                }

                if ($version->pointRuleFor($placementKey, $participation) === null) {
                    throw new \DomainException("No signed-off point rule exists for placement key {$placementKey}.");
                }

                $ranks[] = $rank;
                $entryIds[] = $entryId;
            }

            $revision = (int) $division->placements()->max('revision') + 1;
            $placement = $division->placements()->create([
                'competition_rule_version_id' => $version->getKey(),
                'revision' => $revision,
                'state' => DivisionPlacementState::Submitted,
                'evidence' => $evidence,
                'submitted_by' => $actor->getKey(),
                'submitted_at' => now(),
            ]);

            foreach ($items as $item) {
                $entry = $division->entries()->with('delegation')->whereKey((int) $item['entry_id'])->firstOrFail();
                $participation = (bool) ($item['participation_eligible'] ?? false);
                $rule = $version->pointRuleFor((string) $item['placement_key'], $participation);

                $placement->items()->create([
                    'entry_id' => $entry->getKey(),
                    'event_delegation_id' => $entry->event_delegation_id,
                    'rank' => (int) $item['rank'],
                    'placement_key' => (string) $item['placement_key'],
                    'championship_points' => $rule->points,
                    'participation_eligible' => $participation,
                ]);
            }

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::DivisionPlacementSubmitted,
                $placement,
                event: $event,
                after: [
                    'state' => DivisionPlacementState::Submitted->value,
                    'revision' => $revision,
                    'item_count' => count($items),
                ],
            );

            return $placement->load('items');
        });
    }
}
