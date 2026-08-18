<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\ProvisionEventScorer;
use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function create(Request $request, Event $event): Response
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the active Global Admin can provision a scorer.');
        }

        return Inertia::render('Admin/Accounts/Create', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
        ]);
    }

    public function store(Request $request, Event $event, ProvisionEventScorer $provision): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255'],
            'role' => ['required', Rule::in([EventRole::Judge->value, EventRole::Tabulator->value])],
        ]);
        $result = $provision->handle(
            $request->user(),
            $event,
            $data,
            EventRole::from($data['role']),
        );

        return back()
            ->with('setup_url', route('account.setup', ['token' => $result['token']]))
            ->with('setup_invitation', [
                'name' => $result['user']->name,
                'role' => $data['role'],
                'expires_at' => $result['invitation']->expires_at?->toIso8601String(),
            ]);
    }
}
