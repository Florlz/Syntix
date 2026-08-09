<?php

namespace App\Actions\Scoring;

use App\Enums\AuditAction;
use App\Enums\ContestState;
use App\Models\Contest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RecordLiveScore
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
                throw new \DomainException('Live scores can only be recorded for a live contest.');
            }

            if ((int) $contest->revision !== $expectedRevision) {
                throw new \DomainException('The contest revision is stale.');
            }

            $contest->update([
                'live_payload' => $payload,
                'revision' => $expectedRevision + 1,
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::ContestScoreRecorded,
                $contest,
                before: ['revision' => $expectedRevision],
                after: ['revision' => $contest->revision],
            );

            return $contest->fresh();
        });
    }
}
