<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Models\EventUserRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = EventUserRole::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->with('event')
            ->latest('granted_at')
            ->first();
        $event = $role?->event;

        if ($event === null) {
            return Inertia::render('Dashboard', [
                'event' => null,
                'readiness' => [],
                'pending_approvals' => [],
                'live_contests' => [],
                'schedule' => [],
                'capabilities' => [
                    'event_creator' => $user->hasActivePlatformCapability('event_creator'),
                ],
            ]);
        }

        if ($role->role !== EventRole::Admin && (string) $role->role !== EventRole::Admin->value) {
            return Inertia::render('Dashboard', [
                'event' => [
                    'id' => (string) $event->getKey(),
                    'name' => $event->name,
                    'state' => $event->eventState()->value,
                    'role' => $role->role instanceof EventRole ? $role->role->value : (string) $role->role,
                ],
                'readiness' => [],
                'pending_approvals' => [],
                'live_contests' => [],
                'schedule' => [],
                'capabilities' => [
                    'event_creator' => $user->hasActivePlatformCapability('event_creator'),
                ],
            ]);
        }

        $divisions = $event->competitions()
            ->with(['divisions.ruleVersions', 'divisions.contests'])
            ->get()
            ->flatMap(fn ($competition) => $competition->divisions->map(fn ($division) => [
                'id' => (string) $division->getKey(),
                'competition' => $competition->name,
                'name' => $division->name,
                'rule_versions' => $division->ruleVersions->map(fn ($version) => [
                    'id' => (string) $version->getKey(),
                    'version' => $version->version,
                    'state' => $version->lifecycleState()->value,
                    'blocking_errors' => $version->readinessErrors(),
                ])->values()->all(),
                'contest_count' => $division->contests->count(),
            ]))->values()->all();

        $liveContests = $event->competitions()
            ->with('divisions.contests')
            ->get()
            ->flatMap(fn ($competition) => $competition->divisions->flatMap(
                fn ($division) => $division->contests
                    ->where('state', 'live')
                    ->map(fn ($contest) => [
                        'id' => (string) $contest->getKey(),
                        'competition' => $competition->name,
                        'division' => $division->name,
                        'name' => $contest->name,
                        'revision' => $contest->revision,
                        'updated_at' => $contest->updated_at?->toIso8601String(),
                    ])
            ))->values()->all();

        $pendingApprovals = $event->competitions()
            ->with(['divisions.placements', 'divisions.contests.resultSubmissions'])
            ->get()
            ->flatMap(function ($competition): array {
                return $competition->divisions->flatMap(function ($division) use ($competition): array {
                    $results = $division->contests->flatMap(fn ($contest) => $contest->resultSubmissions
                        ->where('state', 'submitted')
                        ->map(fn ($submission) => [
                            'kind' => 'contest outcome',
                            'id' => (string) $submission->getKey(),
                            'label' => $competition->name.' / '.$division->name.' / '.$contest->name,
                            'status' => 'submitted',
                        ]));
                    $placements = $division->placements
                        ->where('state', 'submitted')
                        ->map(fn ($placement) => [
                            'kind' => 'Division Placement',
                            'id' => (string) $placement->getKey(),
                            'label' => $competition->name.' / '.$division->name,
                            'status' => 'submitted',
                        ]);

                    return [...$results->values()->all(), ...$placements->values()->all()];
                })->values()->all();
            })->values()->all();

        return Inertia::render('Dashboard', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'role' => $role->role instanceof EventRole ? $role->role->value : (string) $role->role,
            ],
            'readiness' => [
                'divisions' => $divisions,
                'total_divisions' => count($divisions),
                'blocked_divisions' => collect($divisions)
                    ->filter(fn (array $division): bool => collect($division['rule_versions'])->contains(
                        fn (array $version): bool => $version['blocking_errors'] !== []
                    ))
                    ->count(),
            ],
            'pending_approvals' => $pendingApprovals,
            'live_contests' => $liveContests,
            'schedule' => [],
            'capabilities' => [
                'event_creator' => $user->hasActivePlatformCapability('event_creator'),
            ],
        ]);
    }
}
