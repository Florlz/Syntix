<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\Contest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CompleteContest
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $actor, Contest $contest, array $payload, int $expectedRevision): Contest
    {
        if (! $actor->canScoreContest($contest)) {
            throw new AuthorizationException('The Tabulator is not assigned to this contest.');
        }

        return DB::transaction(function () use ($actor, $contest, $payload, $expectedRevision): Contest {
            $contest = Contest::query()->whereKey($contest->getKey())->lockForUpdate()->firstOrFail();

            if ($contest->state !== ContestState::Live) {
                throw new \DomainException('Only live contests can be completed.');
            }

            if ((int) $contest->revision !== $expectedRevision) {
                throw new \DomainException('The contest revision is stale.');
            }

            $contest->update([
                'state' => ContestState::Completed,
                'result_payload' => $payload,
                'completed_at' => now(),
                'completed_by' => $actor->getKey(),
                'revision' => $expectedRevision + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestCompleted,
                $contest,
                before: ['state' => ContestState::Live->value, 'revision' => $expectedRevision],
                after: ['state' => ContestState::Completed->value, 'revision' => $contest->revision],
            );

            return $contest->fresh();
        });
    }
}
