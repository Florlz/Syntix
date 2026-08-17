<?php

namespace App\Actions\Brackets;

use App\Enums\AuditAction;
use App\Enums\BracketVersionState;
use App\Enums\CompetitionFormat;
use App\Enums\ContestState;
use App\Enums\TournamentState;
use App\Models\Contest;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\DrawRecord;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TournamentScope;
use App\Support\SeededDraw;
use App\Support\EventOperationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GenerateRandomTournament
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(
        User $actor,
        Division $division,
        ?string $commandUuid = null,
        bool $redraw = false,
        ?Discipline $discipline = null,
    ): Tournament {
        $commandUuid ??= (string) Str::uuid();
        $replay = DrawRecord::query()->with('tournament')->where('command_uuid', $commandUuid)->first();

        if ($replay !== null) {
            return $replay->tournament;
        }

        $division->loadMissing('competition.event');
        $event = $division->competition?->event;

        EventOperationGuard::assertMutable($actor, $event, 'Only the active Global Admin can generate a random draw.');

        return DB::transaction(function () use ($actor, $division, $commandUuid, $redraw, $event, $discipline): Tournament {
            $division = Division::query()->whereKey($division->getKey())->lockForUpdate()->firstOrFail();
            if ($discipline !== null) {
                $discipline = Discipline::query()
                    ->whereKey($discipline->getKey())
                    ->where('competition_division_id', $division->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $scope = new TournamentScope($division, $discipline);
            $replay = DrawRecord::query()->with('tournament')->where('command_uuid', $commandUuid)->lockForUpdate()->first();

            if ($replay !== null) {
                return $replay->tournament;
            }

            $published = $scope->tournamentQuery()->where('state', TournamentState::Published->value)->exists();

            if ($published) {
                throw new \DomainException('A published tournament cannot be redrawn.');
            }

            $previews = $scope->tournamentQuery()
                ->whereIn('state', [TournamentState::Preview->value, TournamentState::Uncontested->value])
                ->with('bracketVersions.nodes')
                ->lockForUpdate()
                ->get();

            if ($previews->isNotEmpty() && ! $redraw) {
                throw new \DomainException('A preview draw already exists. Confirm a redraw to replace it.');
            }

            foreach ($previews as $preview) {
                $preview->bracketVersions()->update(['state' => BracketVersionState::Replaced]);
                $contestIds = $preview->bracketVersions->flatMap->nodes->pluck('contest_id')->filter();
                Contest::query()->whereIn('id', $contestIds)->update([
                    'state' => ContestState::Cancelled,
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Replaced by an approved pre-publication random redraw.',
                ]);
                $preview->update(['state' => TournamentState::Archived]);
            }

            $scope->assertReadyForGeneration();
            $eligibleIds = $scope->eligibleEntryIds();

            $seed = bin2hex(random_bytes(32));
            $drawOrder = SeededDraw::shuffle($eligibleIds->all(), $seed);
            $format = $division->ruleVersions()->where('is_governing', true)->firstOrFail()->format();
            $tournament = match ($format) {
                CompetitionFormat::SingleElimination => (new GenerateSingleEliminationBracket)->handle(
                    $actor,
                    $division,
                    $drawOrder,
                    'cryptographic_random',
                    $discipline,
                ),
                CompetitionFormat::DoubleElimination => (new GenerateDoubleEliminationBracket)->handle(
                    $actor,
                    $division,
                    $drawOrder,
                    'cryptographic_random',
                    $discipline,
                ),
                CompetitionFormat::RoundRobin => (new GenerateRoundRobinSchedule)->handle(
                    $actor,
                    $division,
                    $drawOrder,
                    'cryptographic_random',
                    $discipline,
                ),
                default => throw new \DomainException('This Division format does not use an automated draw.'),
            };

            $tournament->drawRecords()->firstOrFail()->update([
                'command_uuid' => $commandUuid,
                'random_seed' => $seed,
                'algorithm_version' => SeededDraw::ALGORITHM_VERSION,
            ]);

            if ($previews->isNotEmpty()) {
                ($this->audit ?? new AuditLogger)->record(
                    $actor,
                    AuditAction::BracketRedrawn,
                    $tournament,
                    event: $event,
                    after: [
                        'replaced_tournament_ids' => $previews->pluck('id')->all(),
                        'command_uuid' => $commandUuid,
                    ],
                );
            }

            return $tournament->fresh(['drawRecords', 'bracketVersions.nodes.slots']);
        });
    }
}
