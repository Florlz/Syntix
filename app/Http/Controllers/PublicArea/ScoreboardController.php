<?php

namespace App\Http\Controllers\PublicArea;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\StandingCalculator;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    public function __invoke(Event $event, StandingCalculator $standings): Response
    {
        $event->load([
            'competitions.divisions.contests',
            'delegations',
        ]);

        $competitions = $event->competitions->map(fn ($competition) => [
            'id' => (string) $competition->getKey(),
            'name' => $competition->name,
            'divisions' => $competition->divisions->map(fn ($division) => [
                'id' => (string) $division->getKey(),
                'name' => $division->name,
                'contests' => $division->contests->map(fn ($contest) => [
                    'id' => (string) $contest->getKey(),
                    'name' => $contest->name,
                    'state' => $contest->state->value,
                    'revision' => $contest->revision,
                    'live' => self::publicLivePayload($contest->live_payload),
                    'updated_at' => $contest->updated_at?->toIso8601String(),
                    'official' => $contest->currentOfficialOutcome()?->only([
                        'state',
                        'revision',
                        'outcome_type',
                        'approved_at',
                    ]),
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        $leaderboard = $standings->forEvent($event)->map(fn ($delegation) => [
            'id' => (string) $delegation->getKey(),
            'name' => $delegation->name,
            'abbreviation' => $delegation->abbreviation,
            'total' => (string) ($delegation->championship_total ?? '0'),
        ])->values()->all();

        return Inertia::render('Public/Scoreboard', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
            ],
            'competitions' => $competitions,
            'leaderboard' => $leaderboard,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed>|null $payload */
    private static function publicLivePayload(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        return collect($payload)
            ->only(['home', 'away', 'period', 'set', 'round', 'phase', 'status'])
            ->all();
    }
}
