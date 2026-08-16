<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display account and dashboard settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $events = $this->availableEvents($user);

        return Inertia::render('Settings/Index', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'preferences' => $user->normalizedPreferences($events->pluck('id')),
            'events' => $events->map(fn (Event $event): array => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
            ])->values(),
            'preference_options' => [
                'text_sizes' => [
                    ['value' => 'default', 'label' => 'Default'],
                    ['value' => 'large', 'label' => 'Large'],
                    ['value' => 'x-large', 'label' => 'Extra large'],
                ],
                'contrasts' => [
                    ['value' => 'default', 'label' => 'Default contrast'],
                    ['value' => 'high', 'label' => 'High contrast'],
                ],
                'landing_pages' => [
                    ['value' => 'overview', 'label' => 'Overview'],
                    ['value' => 'sports', 'label' => 'Sports Directory'],
                    ['value' => 'departments', 'label' => 'Departments'],
                    ['value' => 'staff', 'label' => 'Event Staff'],
                    ['value' => 'results', 'label' => 'Results'],
                ],
            ],
            'other_session_count' => $this->otherSessionCount($request),
            'status' => session('status'),
        ]);
    }

    /**
     * Save accessibility and dashboard preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $this->assertActive($request);
        $user = $request->user();
        $events = $this->availableEvents($user);

        $validated = $request->validate([
            'text_size' => ['sometimes', 'required', Rule::in(['default', 'large', 'x-large'])],
            'contrast' => ['sometimes', 'required', Rule::in(['default', 'high'])],
            'reduce_motion' => ['sometimes', 'required', 'boolean'],
            'default_event_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::in($events->modelKeys()),
            ],
            'default_landing' => [
                'sometimes',
                'required',
                Rule::in(['overview', 'sports', 'departments', 'staff', 'results']),
            ],
        ]);

        $current = $user->normalizedPreferences();
        $user->preferences = [
            'text_size' => $validated['text_size'] ?? $current['text_size'],
            'contrast' => $validated['contrast'] ?? $current['contrast'],
            'reduce_motion' => array_key_exists('reduce_motion', $validated)
                ? (bool) $validated['reduce_motion']
                : $current['reduce_motion'],
            'default_event_id' => array_key_exists('default_event_id', $validated)
                ? ($validated['default_event_id'] === null ? null : (int) $validated['default_event_id'])
                : $current['default_event_id'],
            'default_landing' => $validated['default_landing'] ?? $current['default_landing'],
        ];
        $user->save();

        return back()->with('status', 'Preferences saved.');
    }

    /**
     * Sign out every database-backed session except the current one.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $this->assertActive($request);

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $deleted = DB::table($this->sessionTable())
            ->where('user_id', $request->user()->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('status', $deleted === 0
            ? 'There were no other sessions to sign out.'
            : "Signed out {$deleted} other session".($deleted === 1 ? '' : 's').'.');
    }

    /**
     * @return Collection<int, Event>
     */
    private function availableEvents(User $user): Collection
    {
        if ($user->isGlobalAdmin()) {
            return Event::query()->latest('created_at')->get();
        }

        $eventIds = $user->eventRoles()->active()->pluck('event_id')->unique();

        return Event::query()
            ->whereIn('id', $eventIds)
            ->latest('created_at')
            ->get();
    }

    private function otherSessionCount(Request $request): int
    {
        return DB::table($this->sessionTable())
            ->where('user_id', $request->user()->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->count();
    }

    private function sessionTable(): string
    {
        return (string) config('session.table', 'sessions');
    }

    private function assertActive(Request $request): void
    {
        if (! $request->user()->isActive()) {
            throw new AuthorizationException('Disabled accounts cannot update settings.');
        }
    }
}
