<?php

namespace App\Actions\Identity;

use App\Enums\AccountState;
use App\Enums\AuditAction;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class EnableUser
{
    public function __construct(private readonly ?AuditLogger $audit = null) {}

    public function handle(User $actor, User $target, ?Event $event = null): User
    {
        return DB::transaction(function () use ($actor, $target, $event): User {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            $hasAdminAccess = $event === null
                ? $actor->hasAnyAdminAccess()
                : $actor->hasAdminAccess($event);

            if (! $actor->isActive() || ! $hasAdminAccess) {
                throw new AuthorizationException('The active Global Admin is required to enable an account.');
            }

            if ($target->isActive()) {
                throw new \DomainException('The account is already active.');
            }

            $before = [
                'account_state' => AccountState::Disabled->value,
                'disabled_at' => $target->disabled_at?->toIso8601String(),
                'disabled_by' => $target->disabled_by,
            ];

            $target->forceFill([
                'account_state' => AccountState::Active,
            ])->save();

            ($this->audit ?? new AuditLogger)->record(
                $actor,
                AuditAction::UserEnabled,
                $target,
                $event,
                before: $before,
                after: [
                    'account_state' => AccountState::Active->value,
                ],
            );

            return $target;
        });
    }
}
