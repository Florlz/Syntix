<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Models\Contest;
use App\Models\JudgedTieResolution;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\JudgeScoreAggregationService;
use App\Support\EventOperationGuard;
use Illuminate\Support\Facades\DB;

final class ResolveJudgedTie
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /** @param list<int> $tiedEntryIds @param list<int> $authorizedOrder */
    public function handle(User $actor, Contest $contest, array $tiedEntryIds, array $authorizedOrder, string $reason, string $reference): JudgedTieResolution
    {
        $contest->loadMissing('division.competition.event');
        $event = $contest->division?->competition?->event;
        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can resolve a judged tie.');

        $tied = array_values(array_unique(array_map('intval', $tiedEntryIds)));
        $order = array_values(array_unique(array_map('intval', $authorizedOrder)));
        sort($tied);
        $sortedOrder = $order;
        sort($sortedOrder);
        if (count($tied) < 2 || $tied !== $sortedOrder) {
            throw new \DomainException('Authorized order must contain every tied entry exactly once.');
        }
        if (trim($reason) === '' || trim($reference) === '') {
            throw new \DomainException('Tie resolution reason and administrative reference are required.');
        }

        return DB::transaction(function () use ($actor, $contest, $event, $tied, $order, $reason, $reference): JudgedTieResolution {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();
            $contest->scorecards()->lockForUpdate()->get();
            $contest->adjustments()->lockForUpdate()->get();
            $current = (new JudgeScoreAggregationService)->aggregate($contest);
            $matchingTie = collect($current['ties'])->first(fn (array $tie): bool => $tie['entry_ids'] === $tied);
            if ($matchingTie === null) {
                throw new \DomainException('The selected entries are not an unresolved current tie.');
            }
            $resolution = JudgedTieResolution::create([
                'contest_id' => $contest->getKey(),
                'tied_entry_ids' => $tied,
                'authorized_order' => $order,
                'comparison_total' => $matchingTie['comparison_total'],
                'reason' => trim($reason),
                'reference' => trim($reference),
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
            ]);
            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::JudgedTieResolved,
                $resolution,
                event: $event,
                after: ['tied_entry_ids' => $tied, 'authorized_order' => $order, 'reference' => trim($reference)],
                reason: trim($reason),
            );

            return $resolution;
        });
    }
}
