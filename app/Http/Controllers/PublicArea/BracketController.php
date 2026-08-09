<?php

namespace App\Http\Controllers\PublicArea;

use App\Enums\BracketVersionState;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Event;
use App\Models\OfficialContestOutcome;
use Inertia\Inertia;
use Inertia\Response;

class BracketController extends Controller
{
    public function __invoke(Event $event, Division $division): Response
    {
        $division->loadMissing('competition');
        abort_unless((int) $division->competition?->event_id === (int) $event->getKey(), 404);

        $bracket = $division->tournaments()
            ->where('state', 'published')
            ->with(['bracketVersions' => fn ($query) => $query
                ->where('state', BracketVersionState::Published->value)
                ->with('nodes.slots.entry.delegation', 'nodes.contest.officialOutcomes')])
            ->latest('published_at')
            ->first()?->bracketVersions
            ->sortByDesc('version')
            ->first();

        abort_if($bracket === null, 404);

        $nodes = $bracket->nodes
            ->sortBy(fn ($node): string => sprintf('%05d-%05d', $node->round_number, $node->sequence))
            ->map(function ($node): array {
                $official = $node->contest?->officialOutcomes
                    ->filter(fn (OfficialContestOutcome $outcome): bool => $outcome->outcomeState()->value === 'approved')
                    ->sortByDesc('revision')
                    ->first();

                return [
                    'id' => (string) $node->getKey(),
                    'key' => $node->node_key,
                    'type' => $node->nodeType()->value,
                    'round' => (int) $node->round_number,
                    'sequence' => (int) $node->sequence,
                    'state' => $node->state,
                    'contest' => $node->contest?->name,
                    'official' => $official === null ? null : [
                        'state' => 'approved',
                        'outcome_type' => $official->outcomeType()->value,
                        'revision' => (int) $official->revision,
                    ],
                    'slots' => $node->slots->sortBy('slot_number')->map(fn ($slot) => [
                        'number' => (int) $slot->slot_number,
                        'label' => self::publicEntryLabel($slot->entry?->delegation, $slot->label),
                    ])->values()->all(),
                ];
            })->values()->all();

        return Inertia::render('Public/Bracket', [
            'event' => ['name' => $event->name, 'slug' => $event->slug],
            'division' => [
                'id' => (string) $division->getKey(),
                'name' => $division->name,
                'competition' => $division->competition?->name,
            ],
            'bracket' => [
                'version' => (int) $bracket->version,
                'published_at' => $bracket->published_at?->toIso8601String(),
                'nodes' => $nodes,
            ],
        ]);
    }

    private static function publicEntryLabel(mixed $delegation, ?string $fallback): string
    {
        if ($delegation !== null) {
            return $delegation->abbreviation ?: $delegation->name;
        }

        return $fallback ?: 'To be determined';
    }
}
