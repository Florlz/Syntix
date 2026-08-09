<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Events\CreateEvent;
use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Events/Create');
    }

    public function store(Request $request, CreateEvent $createEvent): RedirectResponse
    {
        $this->assertEventCreator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);
        $event = $createEvent->handle($request->user(), $data);

        return redirect()->route('dashboard', ['event' => $event->getKey()])
            ->with('status', 'Event created and ready for programme setup.');
    }

    private function assertEventCreator(Request $request): void
    {
        if (! $request->user()->isGlobalAdmin()) {
            throw new AuthorizationException('Only the active Global Admin can create an event.');
        }
    }
}
