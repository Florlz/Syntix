<?php

namespace App\Http\Controllers\PublicArea;

use App\Enums\BracketVersionState;
use App\Enums\ContestState;
use App\Enums\EventState;
use App\Enums\TournamentState;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Event;
use App\Services\StandingCalculator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(StandingCalculator $standings): Response
    {
        $snapshotAt = now()->toIso8601String();
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
                'competitions.publishedCoverImage',
                'competitions.divisions.schedules.currentPublication',
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
                'leaderboard' => [],
                'snapshot_at' => $snapshotAt,
                'updated_at' => $snapshotAt,
            ]);
        }

        $competitions = $event->competitions->map(function ($competition): array {
            $cover = $competition->publishedCoverImage;
            $schedules = $competition->divisions
                ->flatMap(fn ($division) => $division->schedules
                    ->map(fn ($schedule) => $schedule->currentPublication))
                ->filter()
                ->sortBy('starts_at')
                ->take(2)
                ->map(fn ($publication) => [
                    'id' => (string) $publication->getKey(),
                    'title' => $publication->title,
                    'division' => $publication->division_name,
                    'starts_at' => $publication->starts_at?->toIso8601String(),
                    'ends_at' => $publication->ends_at?->toIso8601String(),
                    'status' => $publication->status->value,
                    'venue' => $publication->venue_name === null ? null : [
                        'name' => $publication->venue_name,
                        'location' => $publication->venue_location,
                    ],
                ])->values()->all();

            return [
                'id' => (string) $competition->getKey(),
                'name' => $competition->name,
                'cover' => $cover?->public_path === null ? null : [
                    'url' => Storage::disk('public')->url($cover->public_path),
                    'alt' => $cover->alt_text,
                    'width' => $cover->width,
                    'height' => $cover->height,
                ],
                'schedules' => $schedules,
                'divisions' => $competition->divisions->map(fn ($division) => [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                    'has_published_bracket' => $division->tournaments->contains(
                        fn ($tournament): bool => $tournament->bracketVersions->isNotEmpty()
                    ),
                ])->values()->all(),
            ];
        })->values()->all();

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
        $leaderboard = $standings->forEvent($event)
            ->map(fn ($delegation) => [
                'id' => (string) $delegation->getKey(),
                'name' => $delegation->name,
                'abbreviation' => $delegation->abbreviation,
                'total' => (string) ($delegation->championship_total ?? '0'),
            ])->values()->all();

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
            'leaderboard' => $leaderboard,
            'snapshot_at' => $snapshotAt,
            'updated_at' => $snapshotAt,
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
