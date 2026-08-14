<?php

namespace App\Actions\Registrations;

use App\Enums\AuditAction;
use App\Models\CoachAssignment;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

final class DeactivateCoachAssignment
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function handle(User $actor, Event $event, CoachAssignment $assignment): void
    {
        if (! $actor->hasAdminAccess($event) || $event->isArchived() || (int) $assignment->event_id !== (int) $event->getKey()) throw new AuthorizationException('The assignment must belong to this mutable Event.');
        $assignment->update(['is_active' => false, 'deactivated_at' => now()]);
        $this->audit->record($actor, AuditAction::CoachAssignmentDeactivated, $assignment, $event, before: ['is_active' => true], after: ['is_active' => false]);
    }
}
