<?php

namespace App\Models;

use App\Enums\PlatformCapability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'capability',
    'granted_by',
    'granted_at',
    'revoked_by',
    'revoked_at',
    'reason',
])]
class PlatformCapabilityGrant extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    protected function casts(): array
    {
        return [
            'capability' => PlatformCapability::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
