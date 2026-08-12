<?php

namespace App\Http\Controllers\PublicArea;

use App\Enums\BracketNodeType;
use App\Enums\BracketVersionState;
use App\Enums\ContestState;
use App\Enums\EventState;
use App\Enums\TournamentState;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Contest;
use App\Models\Event;
use App\Models\OfficialContestOutcome;
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
                        ->where('state', BracketVersionState::Published->value)
                        ->with([
                            'nodes.slots.entry.delegation',
                            'nodes.contest.officialOutcomes',
                        ])]),
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
                'divisions' => $competition->divisions->map(function ($division): array {
                    $tournament = $division->tournaments
                        ->sortByDesc('published_at')
                        ->first();
                    $bracket = $tournament?->bracketVersions
                        ->sortByDesc('version')
                        ->first();

                    return [
                        'id' => (string) $division->getKey(),
                        'name' => $division->name,
                        'has_published_bracket' => $bracket !== null,
                        'bracket_preview' => self::publicBracketPreview($bracket, $tournament?->formatValue()->value),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $liveContests = $event->competitions->flatMap(fn ($competition) => $competition->divisions
            ->flatMap(fn ($division) => $division->contests->map(
                fn (Contest $contest) => self::publicContest($contest, $competition, $division->name)
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
                'color' => $delegation->color,
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
    private static function publicContest(Contest $contest, Competition $competition, string $division): array
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
            'competition_id' => (string) $competition->getKey(),
            'competition' => $competition->name,
            'division' => $division,
            'name' => $contest->name,
            'sides' => $sides,
            'live' => self::publicLivePayload($contest->live_payload),
            'revision' => (int) $contest->revision,
            'updated_at' => $contest->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function publicBracketPreview(mixed $bracket, ?string $format): ?array
    {
        if ($bracket === null) {
            return null;
        }

        $nodes = $bracket->nodes
            ->filter(fn ($node): bool => $node->nodeType() === BracketNodeType::Contest)
            ->sortBy(fn ($node): string => sprintf('%05d-%05d', $node->round_number ?? 0, $node->sequence ?? 0));

        $live = $nodes->filter(fn ($node): bool => $node->contest?->state === ContestState::Live);
        $upcoming = $nodes->filter(fn ($node): bool => in_array(
            $node->contest?->state?->value,
            [ContestState::Scheduled->value, 'pending'],
            true,
        ));
        $official = $nodes->filter(fn ($node): bool => $node->contest !== null
            && $node->contest->officialOutcomes->contains(
                fn (OfficialContestOutcome $outcome): bool => $outcome->outcomeState()->value === 'approved'
            ))
            ->sortByDesc(fn ($node): string => sprintf('%05d-%05d', $node->round_number ?? 0, $node->sequence ?? 0));

        $previewNodes = $live->concat($upcoming)->unique('id')->take(3);
        if ($previewNodes->isEmpty()) {
            $previewNodes = $official->take(3);
        }
        if ($previewNodes->isEmpty()) {
            $previewNodes = $nodes->take(3);
        }

        $round = $previewNodes->first()?->round_number;

        return [
            'version' => (int) $bracket->version,
            'format' => $format,
            'round_label' => $round === null ? 'Bracket preview' : 'Round '.(int) $round,
            'matchups' => $previewNodes->map(fn ($node): array => [
                'id' => (string) $node->getKey(),
                'slots' => $node->slots
                    ->sortBy('slot_number')
                    ->map(fn ($slot): array => [
                        'number' => (int) $slot->slot_number,
                        'label' => self::publicEntryLabel($slot->entry?->delegation, $slot->label),
                    ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private static function publicEntryLabel(mixed $delegation, ?string $fallback): string
    {
        if ($delegation !== null) {
            return $delegation->abbreviation ?: $delegation->name;
        }

        return $fallback ?: 'To be determined';
    }
}
