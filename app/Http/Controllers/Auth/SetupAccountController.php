<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SetupAccountController extends Controller
{
    public function create(string $token): Response
    {
        $invitation = $this->invitation($token);

        return Inertia::render('Auth/SetupAccount', [
            'valid' => $invitation !== null,
            'email' => $invitation?->user?->email,
        ]);
    }

    public function store(Request $request, string $token, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        return DB::transaction(function () use ($request, $token, $data, $audit): RedirectResponse {
            $invitation = $this->invitation($token, lock: true);

            if ($invitation === null) {
                return back()->withErrors(['token' => 'This setup link is expired or has already been used.']);
            }

            $user = $invitation->user->fresh();
            $user->update([
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);
            $invitation->update(['consumed_at' => now()]);
            Auth::login($user);
            $request->session()->regenerate();
            $audit->record(
                $user,
                AuditAction::InvitationConsumed,
                $invitation,
                event: $invitation->event,
                after: ['consumed' => true],
            );

            return redirect()->route('dashboard');
        });
    }

    private function invitation(string $token, bool $lock = false): ?UserInvitation
    {
        if ($token === '' || ! preg_match('/^[A-Za-z0-9]+$/', $token)) {
            return null;
        }

        $query = UserInvitation::query()
            ->with(['user', 'event'])
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
