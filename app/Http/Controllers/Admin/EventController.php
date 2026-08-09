<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\GrantEventRole;
use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function create(Request $request): Response
    {
        $this->assertEventCreator($request);

        return Inertia::render('Admin/Events/Create', [
            'active_users' => User::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => (string) $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request, CreateEvent $createEvent, GrantEventRole $grantRole): RedirectResponse
    {
        $this->assertEventCreator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'first_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $event = $createEvent->handle($request->user(), $data);

        if (! empty($data['first_admin_id'])) {
            $firstAdmin = User::query()->active()->findOrFail($data['first_admin_id']);
            $grantRole->handle($request->user(), $event, $firstAdmin, EventRole::Admin);
        }

        return redirect()->route('dashboard')->with('status', 'Event shell created and audited.');
    }

    private function assertEventCreator(Request $request): void
    {
        if (! $request->user()->hasActivePlatformCapability('event_creator')) {
            throw new AuthorizationException('Only a platform event creator can create an event.');
        }
    }
}
