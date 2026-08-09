<?php

namespace App\Actions\Identity;

use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Models\Event;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ProvisionUser
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    /**
     * @param  array{name: string, email: string}  $attributes
     * @return array{user: User, invitation: UserInvitation, token: string}
     */
    public function handle(User $actor, Event $event, array $attributes): array
    {
        if (! $actor->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can provision a user.');
        }

        return DB::transaction(function () use ($actor, $event, $attributes): array {
            $email = strtolower(trim((string) ($attributes['email'] ?? '')));

            if ($email === '' || User::query()->where('email', $email)->exists()) {
                throw new \DomainException('An account with this institutional email already exists or the email is invalid.');
            }

            $user = User::create([
                'name' => trim((string) ($attributes['name'] ?? '')),
                'email' => $email,
                'password' => Hash::make(Str::random(96)),
                'email_verified_at' => null,
            ]);
            $token = Str::random(64);
            $invitation = UserInvitation::create([
                'user_id' => $user->getKey(),
                'event_id' => $event->getKey(),
                'token_hash' => hash('sha256', $token),
                'invited_by' => $actor->getKey(),
                'expires_at' => now()->addHours(24),
            ]);

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::UserProvisioned,
                $user,
                event: $event,
                after: [
                    'user_id' => $user->getKey(),
                    'email' => $user->email,
                    'invitation_expires_at' => $invitation->expires_at?->toIso8601String(),
                ],
            );

            return compact('user', 'invitation', 'token');
        });
    }
}
