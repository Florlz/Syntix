<?php

namespace App\Models;

use App\Enums\RosterMemberRole;
use Database\Factories\RosterMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entry_id',
    'participant_id',
    'role',
    'display_order',
    'is_active',
    'notes',
])]
class RosterMember extends Model
{
    /** @use HasFactory<RosterMemberFactory> */
    use HasFactory;

    protected $table = 'entry_members';

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function roleType(): RosterMemberRole
    {
        $role = $this->getAttribute('role');

        return $role instanceof RosterMemberRole
            ? $role
            : RosterMemberRole::from((string) $role);
    }

    protected function casts(): array
    {
        return [
            'role' => RosterMemberRole::class,
            'is_active' => 'boolean',
        ];
    }
}
