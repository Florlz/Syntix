# ADR 0002: Event Roles And Scoring Assignments

## Status

Accepted

## Date

2026-08-09

## Context

Event responsibility and permission to score a specific Division are different facts. Treating a role as blanket scoring access would expose unrelated Divisions and prevent safe multi-Division staffing. Event creation also occurs before an Event Role can exist, so its authorization cannot be derived from an Event Admin role.

## Decision

Admin, Judge, and Tabulator are distinct authenticated Event Roles scoped to one Event. Scoring Assignments are separate, explicit, active, and revocable records. The only assignment scope types are:

- `competition_division`: all current and future Contests directly belonging to one exact Division within one Competition.
- `contest`: one exact Contest and no descendants.
- `entry_scorecard`: one exact Entry Scorecard and no siblings or parents.

Containment is evaluated from authoritative Event, Competition, Division, Contest, and Entry Scorecard relationships. The only parent-to-child inheritance is from `competition_division` to Contests directly belonging to that Division, including Contests created later. A `contest` assignment never extends to another Contest or to an Entry Scorecard, and an Entry Scorecard requires its own `entry_scorecard` assignment. No scope grants access to a parent or sibling. There is no implicit `competition`, `discipline`, wildcard, custom, or `other` scope.

Every Judge or Tabulator scoring read and mutation requires both the applicable active Event Role and at least one matching active Scoring Assignment. The Event Role still limits the kind of operation: an assignment does not let a Judge perform Tabulator operations or vice versa. Overlapping assignments grant the union of their scopes without widening containment. Revoking one assignment removes only its grant; access remains when another active assignment independently matches. Revoking the Event Role removes all scoring authority immediately. Queued commands are reauthorized when received.

Public access is anonymous and read-only. It is not stored as an Event Role or Scoring Assignment. Privileged accounts use closed provisioning: an Admin creates or invites accounts and assigns roles; open self-registration cannot provision Admin, Judge, or Tabulator access.

Event creation requires the separate platform-level `event_creator` capability. It permits an active capability holder to create an Event shell but grants no Event Role automatically. The holder must grant the new Event's first Admin Event Role to an active user, which may be the same user; that first-Admin grant is allowed only while the Event has no active Admin. An Event Admin cannot create another Event unless that account separately has `event_creator`.

The initial deployment bootstrap context is narrower and distinct from both `event_creator` and Event Admin. It may create the first active user with `event_creator`, records the deployment actor or context, and is then permanently disabled or becomes a no-op. It cannot administer or score an Event.

## Consequences

- Policies and server-side queries must enforce role and assignment checks, not only navigation visibility.
- Assignment and Event Role revocation deny new access immediately; queued commands are reauthorized when received.
- Role and assignment changes require audit records.
- An Admin role grants event administration but does not implicitly create a Judge or Tabulator assignment.
- Deployment bootstrap and platform capability checks remain separate from Event policies.

## Rejected Alternatives

- One global user role: rejected because responsibilities and access vary by Event.
- Event Role alone grants all scoring access: rejected because it violates least privilege.
- Store each Division as a separate Tabulator role: rejected because roles describe responsibility while assignments describe scope.
- Generic or `other` assignment scopes: rejected because they make containment and authorization review ambiguous.
- Let Event Admin imply event creation authority: rejected because an Event Role must not grant cross-Event platform authority.
- Let deployment bootstrap create an Event Admin: rejected because bootstrap establishes the first platform event creator, while first-Admin provisioning is a later audited action.
- Store Public as a role or allow open privileged registration: rejected because public access is anonymous and privileged access is controlled.

## References

- [SYNTIX product and domain contract](../cspc-siklab-plan.md)
- [SYNTIX Domain Glossary](../domain-glossary.md)
