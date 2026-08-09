<?php

namespace App\Models;

use App\Enums\InvitationState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event_id', 'token_hash', 'invited_by', 'expires_at', 'consumed_at'])]
class UserInvitation extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitationState(): InvitationState
    {
        if ($this->consumed_at !== null) {
            return InvitationState::Consumed;
        }

        return $this->expires_at?->isPast() === true
            ? InvitationState::Expired
            : InvitationState::Pending;
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
