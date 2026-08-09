<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\ProvisionUser;
use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function create(Request $request, Event $event): Response
    {
        if (! $request->user()->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can provision a user.');
        }

        return Inertia::render('Admin/Accounts/Create', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
        ]);
    }

    public function store(Request $request, Event $event, ProvisionUser $provision): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255'],
        ]);
        $result = $provision->handle($request->user(), $event, $data);

        // The raw token is returned only to the initiating Admin response. It
        // is never persisted, logged, or included in the shared auth DTO.
        return back()->with('setup_url', route('account.setup', ['token' => $result['token']]));
    }
}
