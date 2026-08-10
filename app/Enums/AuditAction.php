<?php

namespace App\Enums;

enum AuditAction: string
{
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';
    case UserSessionsRevoked = 'user.sessions_revoked';
    case EventCreatorBootstrapped = 'event_creator.bootstrapped';
    case GlobalAdminBootstrapped = 'global_admin.bootstrapped';
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
    case BracketRedrawn = 'bracket.redrawn';
    case BracketPublished = 'bracket.published';
    case DisciplinePlacementApproved = 'discipline_placement.approved';
    case DivisionSubPointsCommitted = 'division_sub_points.committed';
    case CompetitionCreated = 'competition.created';
    case CompetitionUpdated = 'competition.updated';
    case CompetitionStateChanged = 'competition.state_changed';
    case DivisionCreated = 'division.created';
    case DivisionUpdated = 'division.updated';
    case DivisionStateChanged = 'division.state_changed';
    case InvitationReissued = 'invitation.reissued';
    case RuleVersionCreated = 'rule_version.created';
    case ProgrammeApplied = 'programme.applied';
    case UserProvisioned = 'user.provisioned';
    case InvitationConsumed = 'invitation.consumed';
    case VenueCreated = 'venue.created';
    case VenueUpdated = 'venue.updated';
    case ScheduleCreated = 'schedule.created';
    case ScheduleUpdated = 'schedule.updated';
    case SchedulePublished = 'schedule.published';
    case SchedulePublicationWithdrawn = 'schedule_publication.withdrawn';
    case CompetitionCoverUploaded = 'competition_cover.uploaded';
    case CompetitionCoverPublished = 'competition_cover.published';
    case CompetitionCoverWithdrawn = 'competition_cover.withdrawn';
    case ParticipantCreated = 'participant.created';
    case ParticipantUpdated = 'participant.updated';
    case EntryCreated = 'entry.created';
    case EntryUpdated = 'entry.updated';
    case EntryStatusChanged = 'entry.status_changed';
    case RosterMembershipSaved = 'roster_membership.saved';
    case EligibilitySet = 'eligibility.set';
}
