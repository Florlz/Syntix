<?php

namespace App\Http\Middleware;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\DivisionPlacement;
use App\Models\EligibilityRecord;
use App\Models\ResultSubmission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $anonymousPublic = $request->is('events/*/scoreboard', 'events/*/divisions/*/bracket');
        $public = $anonymousPublic || $request->is('/');
        $shared = parent::share($request);

        if ($public) {
            $shared['errors'] = (object) [];
        }

        $user = $anonymousPublic ? null : $request->user();
        $activeRole = $user?->eventRoles()
            ->active()
            ->with('event')
            ->latest('granted_at')
            ->first();
        $activeEvent = $activeRole?->event;
        $globalAdmin = $user?->isGlobalAdmin() ?? false;

        if ($activeEvent === null && $globalAdmin) {
            $routeEvent = $request->route('event');
            $requestedEventId = $routeEvent instanceof Event
                ? $routeEvent->getKey()
                : $request->integer('event');
            $activeEvent = $requestedEventId
                ? Event::query()->find($requestedEventId)
                : null;
            $activeEvent ??= Event::query()->latest('created_at')->first();
        }

        $roles = $activeEvent === null
            ? []
            : $user->eventRoles()
                ->active()
                ->where('event_id', $activeEvent->getKey())
                ->pluck('role')
                ->map(fn (EventRole|string $role): string => $role instanceof EventRole ? $role->value : (string) $role)
                ->values()
                ->all();

        $platformCapabilities = $user?->platformCapabilities()
            ->active()
            ->get()
            ->map(fn ($grant): string => $grant->capability->value)
            ->values()
            ->all() ?? [];

        $navBadges = ['eligibility' => 0, 'staff' => 0, 'results' => 0];
        if ($globalAdmin && $activeEvent !== null) {
            $eventId = $activeEvent->getKey();
            $navBadges['eligibility'] = EligibilityRecord::query()
                ->where('status', 'pending')
                ->where('event_id', $eventId)
                ->count();
            $staffUsers = $activeEvent->userRoles()->active()->whereIn('role', ['judge', 'tabulator'])->pluck('user_id')->unique();
            $assignedUsers = $activeEvent->assignments()->active()->pluck('user_id')->unique();
            $navBadges['staff'] = $staffUsers->diff($assignedUsers)->count();
            $navBadges['results'] = ResultSubmission::query()->where('state', 'submitted')->whereHas('contest.division.competition', fn ($query) => $query->where('event_id', $eventId))->count()
                + DivisionPlacement::query()->where('state', 'submitted')->whereHas('division.competition', fn ($query) => $query->where('event_id', $eventId))->count();
        }

        return [
            ...$shared,
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => (string) $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified_at !== null,
                ],
                'active_event' => $activeEvent === null ? null : [
                    'id' => (string) $activeEvent->getKey(),
                    'name' => $activeEvent->name,
                    'roles' => $roles,
                ],
                'platform_capabilities' => $platformCapabilities,
                'global_admin' => $globalAdmin,
                'capabilities' => array_values(array_unique([
                    ...$platformCapabilities,
                    ...$roles,
                    ...($globalAdmin ? ['global_admin'] : []),
                ])),
            ],
            'flash' => $public || $user === null ? [
                'status' => null,
                'setup_url' => null,
            ] : [
                'status' => $request->session()->get('status'),
                'setup_url' => $request->session()->get('setup_url'),
            ],
            'nav_badges' => $navBadges,
        ];
    }
}
