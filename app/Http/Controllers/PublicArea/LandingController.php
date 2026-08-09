<?php

namespace App\Http\Controllers\PublicArea;

use App\Enums\BracketVersionState;
use App\Enums\ContestState;
use App\Enums\EventState;
use App\Enums\TournamentState;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $event = Event::query()
            ->where('state', EventState::Live->value)
            ->with([
                'competitions' => fn ($query) => $query->orderBy('name'),
                'competitions.divisions' => fn ($query) => $query->orderBy('name'),
                'competitions.divisions.contests' => fn ($query) => $query
                    ->where('state', ContestState::Live->value)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id'),
                'competitions.divisions.contests.entries.entry.delegation',
                'competitions.divisions.tournaments' => fn ($query) => $query
                    ->where('state', TournamentState::Published->value)
                    ->with(['bracketVersions' => fn ($query) => $query
                        ->where('state', BracketVersionState::Published->value)]),
            ])
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($event === null) {
            return Inertia::render('Welcome', [
                'featured_event' => null,
                'featured_contest' => null,
                'live_contests' => [],
                'competitions' => [],
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        $competitions = $event->competitions->map(fn ($competition) => [
            'id' => (string) $competition->getKey(),
            'name' => $competition->name,
            'divisions' => $competition->divisions->map(fn ($division) => [
                'id' => (string) $division->getKey(),
                'name' => $division->name,
                'has_published_bracket' => $division->tournaments->contains(
                    fn ($tournament): bool => $tournament->bracketVersions->isNotEmpty()
                ),
            ])->values()->all(),
        ])->values()->all();

        $liveContests = $event->competitions->flatMap(fn ($competition) => $competition->divisions
            ->flatMap(fn ($division) => $division->contests->map(
                fn (Contest $contest) => self::publicContest($contest, $competition->name, $division->name)
            ))
        )->sort(function (array $left, array $right): int {
            $updatedAt = ($right['updated_at'] ?? '') <=> ($left['updated_at'] ?? '');

            return $updatedAt !== 0
                ? $updatedAt
                : ((int) $right['id'] <=> (int) $left['id']);
        })->values();
        $featuredContest = $liveContests->first();

        return Inertia::render('Welcome', [
            'featured_event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'slug' => $event->slug,
                'state' => $event->eventState()->value,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'featured_contest' => $featuredContest,
            'live_contests' => $liveContests->skip(1)->values()->all(),
            'competitions' => $competitions,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed>|null $payload */
    private static function publicLivePayload(?array $payload): array
    {
        return collect($payload ?? [])
            ->only(['home', 'away', 'period', 'set', 'round', 'phase', 'status'])
            ->all();
    }

    /** @return array<string, mixed> */
    private static function publicContest(Contest $contest, string $competition, string $division): array
    {
        $sides = $contest->entries
            ->sortBy('slot')
            ->take(2)
            ->values()
            ->map(fn ($contestEntry, int $index) => [
                'position' => $index === 0 ? 'home' : 'away',
                'label' => $contestEntry->entry?->delegation?->abbreviation
                    ?: $contestEntry->entry?->delegation?->name
                    ?: ($index === 0 ? 'Home' : 'Away'),
            ])->all();

        return [
            'id' => (string) $contest->getKey(),
            'competition' => $competition,
            'division' => $division,
            'name' => $contest->name,
            'sides' => $sides,
            'live' => self::publicLivePayload($contest->live_payload),
            'revision' => (int) $contest->revision,
            'updated_at' => $contest->updated_at?->toIso8601String(),
        ];
    }
}
