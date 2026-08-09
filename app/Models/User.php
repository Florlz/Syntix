<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\EventRole;
use App\Enums\PlatformCapability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'email',
    'password',
    'account_state',
    'is_global_admin',
    'disable_reason',
    'disabled_at',
    'disabled_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function platformCapabilities(): HasMany
    {
        return $this->hasMany(PlatformCapabilityGrant::class);
    }

    public function eventRoles(): HasMany
    {
        return $this->hasMany(EventUserRole::class);
    }

    public function scoringAssignments(): HasMany
    {
        return $this->hasMany(ScoringAssignment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'disabled_by');
    }

    public function isActive(): bool
    {
        return $this->accountState() === AccountState::Active;
    }

    public function isDisabled(): bool
    {
        return ! $this->isActive();
    }

    public function accountState(): AccountState
    {
        $state = $this->getAttribute('account_state');

        if ($state instanceof AccountState) {
            return $state;
        }

        if ($state === null) {
            return AccountState::Active;
        }

        return AccountState::tryFrom((string) $state) ?? AccountState::Disabled;
    }

    public function hasActivePlatformCapability(PlatformCapability|string $capability): bool
    {
        $capability = $capability instanceof PlatformCapability
            ? $capability
            : PlatformCapability::from($capability);

        return $this->isActive()
            && $this->platformCapabilities()
                ->where('capability', $capability->value)
                ->whereNull('revoked_at')
                ->exists();
    }

    public function hasActiveEventRole(Event|int $event, EventRole|string $role): bool
    {
        $role = $role instanceof EventRole ? $role : EventRole::from($role);
        $eventId = $event instanceof Event ? $event->getKey() : $event;

        return $this->isActive()
            && $this->eventRoles()
                ->where('event_id', $eventId)
                ->where('role', $role->value)
                ->whereNull('revoked_at')
                ->exists();
    }

    public function isGlobalAdmin(): bool
    {
        return $this->isActive() && (bool) $this->getAttribute('is_global_admin');
    }

    public function hasAdminAccess(Event|int $event): bool
    {
        return $this->isGlobalAdmin();
    }

    public function hasAnyAdminAccess(): bool
    {
        return $this->isGlobalAdmin();
    }

    public function canScoreContest(Contest $contest): bool
    {
        if (! $this->isActive() || ! $contest->exists) {
            return false;
        }

        $eventId = $contest->eventId();

        $event = $eventId === null ? null : Event::query()->find($eventId);

        if ($event === null
            || $event->isArchived()
            || ! $this->hasActiveEventRole($eventId, EventRole::Tabulator)) {
            return false;
        }

        return $this->scoringAssignments()
            ->active()
            ->where('event_id', $eventId)
            ->where(function (Builder $query) use ($contest): void {
                $query
                    ->where(function (Builder $query) use ($contest): void {
                        $query
                            ->where('scope_type', 'competition_division')
                            ->where('competition_division_id', $contest->competition_division_id);
                    })
                    ->orWhere(function (Builder $query) use ($contest): void {
                        $query
                            ->where('scope_type', 'contest')
                            ->where('contest_id', $contest->getKey());
                    });
            })
            ->exists();
    }

    public function canScoreEntryScorecard(EntryScorecard $scorecard): bool
    {
        if (! $this->isActive() || ! $scorecard->exists) {
            return false;
        }

        $eventId = $scorecard->eventId();

        $event = $eventId === null ? null : Event::query()->find($eventId);

        if ($event === null
            || $event->isArchived()
            || ! $this->hasActiveEventRole($eventId, EventRole::Judge)) {
            return false;
        }

        return $this->scoringAssignments()
            ->active()
            ->where('event_id', $eventId)
            ->where(function (Builder $query) use ($scorecard): void {
                $query->where(function (Builder $query) use ($scorecard): void {
                    $query
                        ->where('scope_type', 'entry_scorecard')
                        ->where('entry_scorecard_id', $scorecard->getKey());
                })->orWhere(function (Builder $query) use ($scorecard): void {
                    $query
                        ->where('scope_type', 'competition_division')
                        ->where('competition_division_id', $scorecard->contest?->competition_division_id);
                });
            })
            ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('account_state', AccountState::Active->value);
    }

    /**
     * Eloquent's provider checks this value during login. Returning an
     * unusable hash makes a disabled account fail authentication without
     * hiding its historical model or audit references from normal queries.
     */
    public function getAuthPassword(): string
    {
        if ($this->isDisabled()) {
            return Hash::make(Str::random(64));
        }

        return (string) $this->getAttribute('password');
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_state' => AccountState::class,
            'is_global_admin' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }
}
