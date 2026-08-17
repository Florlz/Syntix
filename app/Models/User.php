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
use Illuminate\Support\Collection;
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
    'preferences',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Keep account preferences deliberately small and predictable. These
     * values are also used when a legacy/null JSON value is encountered.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_PREFERENCES = [
        'theme' => 'system',
        'text_size' => 'default',
        'contrast' => 'default',
        'reduce_motion' => false,
        'default_event_id' => null,
        'default_landing' => 'overview',
        'notifications' => [
            'approvals' => true,
            'security' => true,
        ],
    ];

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

    public function userInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
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

    /**
     * Return safe preference values for this account.
     *
     * When an event list is provided, a deleted or no-longer-accessible
     * default event falls back to the first available event. The raw JSON is
     * never returned directly to the browser.
     *
     * @param  Collection<int, int|string>|array<int, int|string>|null  $accessibleEventIds
     * @return array<string, mixed>
     */
    public function normalizedPreferences(Collection|array|null $accessibleEventIds = null): array
    {
        $stored = $this->getAttribute('preferences');
        $stored = is_array($stored) ? $stored : [];

        $theme = in_array($stored['theme'] ?? null, ['light', 'dark', 'system'], true)
            ? $stored['theme']
            : self::DEFAULT_PREFERENCES['theme'];
        $textSize = in_array($stored['text_size'] ?? null, ['default', 'large', 'x-large'], true)
            ? $stored['text_size']
            : self::DEFAULT_PREFERENCES['text_size'];
        $contrast = in_array($stored['contrast'] ?? null, ['default', 'high'], true)
            ? $stored['contrast']
            : self::DEFAULT_PREFERENCES['contrast'];
        $defaultLanding = in_array($stored['default_landing'] ?? null, [
            'overview',
            'sports',
            'departments',
            'staff',
            'results',
        ], true)
            ? $stored['default_landing']
            : self::DEFAULT_PREFERENCES['default_landing'];

        $defaultEventId = $stored['default_event_id'] ?? null;
        if ($defaultEventId !== null && filter_var($defaultEventId, FILTER_VALIDATE_INT) === false) {
            $defaultEventId = null;
        } elseif ($defaultEventId !== null) {
            $defaultEventId = (string) ((int) $defaultEventId);
        }

        if ($accessibleEventIds !== null) {
            $available = collect($accessibleEventIds)
                ->map(fn ($id): string => (string) $id)
                ->filter(fn (string $id): bool => $id !== '')
                ->values();

            if ($defaultEventId === null || ! $available->contains($defaultEventId)) {
                $defaultEventId = $available->first();
            }
        }

        $reduceMotion = $stored['reduce_motion'] ?? null;
        $reduceMotion = is_bool($reduceMotion)
            ? $reduceMotion
            : ((is_int($reduceMotion) || is_string($reduceMotion))
                ? filter_var($reduceMotion, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null);

        $storedNotifications = is_array($stored['notifications'] ?? null)
            ? $stored['notifications']
            : [];
        $approvals = $storedNotifications['approvals'] ?? null;
        $approvals = is_bool($approvals)
            ? $approvals
            : ((is_int($approvals) || is_string($approvals))
                ? filter_var($approvals, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null);

        return [
            'theme' => $theme,
            'text_size' => $textSize,
            'contrast' => $contrast,
            'reduce_motion' => $reduceMotion ?? self::DEFAULT_PREFERENCES['reduce_motion'],
            'default_event_id' => $defaultEventId,
            'default_landing' => $defaultLanding,
            'notifications' => [
                'approvals' => $approvals ?? self::DEFAULT_PREFERENCES['notifications']['approvals'],
                'security' => true,
            ],
        ];
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

        if ($scorecard->judge_id === null || (int) $scorecard->judge_id !== (int) $this->getKey()) {
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
            ->where('scope_type', 'entry_scorecard')
            ->where('entry_scorecard_id', $scorecard->getKey())
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
            'preferences' => 'array',
        ];
    }
}
