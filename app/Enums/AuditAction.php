<?php

namespace App\Enums;

enum AuditAction: string
{
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';
    case UserSessionsRevoked = 'user.sessions_revoked';
    case EventCreatorBootstrapped = 'event_creator.bootstrapped';
    case PlatformCapabilityGranted = 'platform_capability.granted';
    case PlatformCapabilityRevoked = 'platform_capability.revoked';
    case EventCreated = 'event.created';
    case EventDelegationCreated = 'event_delegation.created';
    case EventRoleGranted = 'event_role.granted';
    case EventRoleRevoked = 'event_role.revoked';
    case ScoringAssignmentGranted = 'scoring_assignment.granted';
    case ScoringAssignmentRevoked = 'scoring_assignment.revoked';
    case RuleVersionActivated = 'rule_version.activated';
    case ContestStarted = 'contest.started';
    case ContestScoreRecorded = 'contest.score_recorded';
    case ContestCompleted = 'contest.completed';
    case ResultSubmitted = 'result.submitted';
    case ResultRejected = 'result.rejected';
    case ContestOutcomeApproved = 'contest_outcome.approved';
    case DivisionPlacementSubmitted = 'division_placement.submitted';
    case DivisionPlacementApproved = 'division_placement.approved';
    case DivisionPlacementVoided = 'division_placement.voided';
    case LedgerEntryCommitted = 'ledger_entry.committed';
    case BracketGenerated = 'bracket.generated';
    case BracketPublished = 'bracket.published';
    case DisciplinePlacementApproved = 'discipline_placement.approved';
    case DivisionSubPointsCommitted = 'division_sub_points.committed';
    case CompetitionCreated = 'competition.created';
    case DivisionCreated = 'division.created';
    case RuleVersionCreated = 'rule_version.created';
    case UserProvisioned = 'user.provisioned';
    case InvitationConsumed = 'invitation.consumed';
}
