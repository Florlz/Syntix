# Identity and RBAC Specification

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines identity and authorization requirements from the [product and domain contract](../cspc-siklab-plan.md). It does not restate competition authorization details owned by the scoring and tournament specifications.

Source priority follows the product contract:

1. Product-owner decisions recorded in the product contract and decision register.
2. `docs/Approved-2025-Intramurals-Proposal.pdf` for official roles and institutional authority.
3. `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` for RBAC, privacy, security testing, and authorized-access objectives.
4. `docs/SYNTIX.docx` for Admin, Judge, Tabulator, and Public workflows.
5. The repository for the authoritative Laravel 13, Inertia, React, PostgreSQL, and session-authentication stack.

Where a source describes Students as a system role, the confirmed product decision supersedes it: Public access is anonymous and read-only and is not a stored event role.

## Problem

The current Laravel foundation permits open registration and has an inconsistent verified-email middleware setup. SYNTIX accounts can enter, review, and approve institutionally significant results, so privileged access cannot depend on public self-registration, navigation hiding, or a single global role. The product needs closed provisioning, event-scoped roles, assignment-scoped scoring access, prompt revocation, safe session handling, and auditable administration without exposing private authenticated data through Inertia or PWA caches.

## Goals

- Permit privileged accounts only through an authorized provisioning process.
- Separate the platform-level ability to create events from event-scoped Admin membership.
- Separate account status, event role membership, and scoring assignment.
- Enforce every authenticated action on the server through Laravel policies and assignment checks.
- Support one Tabulator with multiple explicit assignments without granting event-wide scoring access.
- Revoke disabled users, roles, assignments, and sessions predictably.
- Give Inertia clients a minimal, explicit authentication DTO rather than a serialized User model.
- Audit security-relevant identity and authorization changes.
- Keep authenticated responses private and out of shared or service-worker caches.

## Non-Goals

- Public account registration or student self-service registration.
- Social login, institutional SSO, API tokens, or third-party identity federation in the initial release.
- Public-user profiles or a stored Public event role.
- Participant eligibility, roster authorization, scoring rules, or bracket behavior except where access control applies.
- Self-service account hard deletion.
- A complete institutional retention policy, which requires CSPC approval.

## Users and Authorization

### Account State

An authenticated person has one `users` record with an account state of `active` or `disabled`.

- `active` permits authentication but grants no event access by itself.
- `disabled` blocks new authentication and all authenticated requests, revokes active sessions, and leaves historical actor references intact.
- Deactivation is preferred to deletion for accounts referenced by audit, score, submission, or approval records.
- A user cannot hard-delete their own account. The initial product exposes no hard-delete action for privileged accounts.

### Event Roles

`event_user_roles` records whether an active user is an Admin, Judge, or Tabulator for one event edition. Roles are event-scoped and independently revocable.

- Admin manages the event and its authorized accounts, roles, and assignments.
- Judge scores only assigned criteria-based work.
- Tabulator scores only objective contests reached by an exact active assignment.
- Public uses anonymous sanitized routes and has no `event_user_roles` record.

The same user may hold roles in different events. Whether one user may hold more than one role in the same event is an open institutional decision. Authorization must evaluate the role for the target event, not a global label.

### Platform Event Creator Capability

`event_creator` is an explicit platform-level capability, not an event role and not an implied power of an event Admin.

- An active user with an active `event_creator` capability may create a new event edition.
- Creating an event grants no event role automatically. The event creator must grant the new event's first Admin role to an active user, which may be the creator.
- If no suitable active user exists, the event creator may issue one closed invitation solely for the first-Admin grant; this does not grant general account-administration power.
- The first-Admin grant is limited to an event that does not yet have an active Admin. After that grant, event-scoped Admin workflows govern additional event roles and accounts.
- Any active event creator may create later events and may grant or revoke `event_creator` for active users through an audited platform-administration action.
- An event Admin without `event_creator` cannot create an event, grant the platform capability, or administer another event.
- The system blocks disabling the last active event creator and blocks revoking that user's capability until another active event creator exists.

### Scoring Assignments

`scoring_assignments.scope_type` is a closed enum with exactly `competition_division`, `contest`, and `entry_scorecard`. An assignment identifies exactly one target for its scope and does not replace an event role.

| Scope | Access granted while active | No access granted |
|---|---|---|
| `competition_division` | All current and future contests directly belonging to that exact division | Parent Competition administration, sibling divisions, disciplines or scorecards not reached through a direct contest |
| `contest` | One exact contest | Its parent division or Competition, sibling contests, descendants, or scorecards |
| `entry_scorecard` | One exact judged entry scorecard | Its entry generally, sibling scorecards, parent contest, division, or Competition |

- Judge and Tabulator scoring access requires an active account, the matching active event role, and a matching active assignment.
- One Tabulator may hold multiple assignments. Each assignment is separately created, audited, and revocable.
- A role without an assignment grants no scoring read or mutation access.
- An assignment without the matching role grants no access.
- A target outside every matching active assignment remains denied even when it belongs to an event where the user is a Tabulator.
- Admin access is governed by an active Admin role for the target event and does not require a scoring assignment for administrative review.
- Narrow scopes never inherit to a parent or sibling. No implicit `competition`, `discipline`, wildcard, or `other` scope exists.
- Overlapping active assignments are allowed. Revoking one assignment removes access only when no other active assignment still matches the requested target.

### Approval Conflict of Duty

The institution has not decided whether an Admin who submitted or materially edited a result may approve it.

Recommended policy: no self-approval. The approving Admin must not be the result submitter and must not be the actor who made the latest scoring correction under review. If staffing makes this impossible, a specifically authorized override must require a reason and a distinct audit event. This recommendation remains non-binding until CSPC approves it.

## Workflow

### Initial Deployment Bootstrap

1. Deployment creates the first active user with the platform-level `event_creator` capability through the approved bootstrap mechanism.
2. The mechanism requires a unique institutional email, creates no published default password, and records the bootstrap actor or deployment context.
3. The first event creator completes secure password setup and signs in.
4. The bootstrap mechanism is permanently disabled or becomes a no-op after the first active `event_creator` capability has been created.
5. The event creator creates the first event and selects an active user, or issues the narrowly scoped closed invitation, to receive that event's first Admin role.
6. The first event Admin provisions subsequent event accounts and roles through the closed Admin workflow.

The exact bootstrap mechanism is open. Recommended path: a one-time, production-safe Artisan command run by an authorized deployer, with secrets entered at execution or a password-setup link sent to the institutional email. Do not ship a seeded default credential, create an implicit global Admin, or leave a web bootstrap route open.

### Later Event Creation

1. An active event creator creates the event shell and records its edition identity.
2. The creator grants the event's first Admin role to an active user; the grant is audited separately from event creation.
3. The first event Admin completes event configuration and manages later event-scoped accounts, roles, and assignments.
4. Neither event creation nor first-Admin grant gives the event creator scoring access unless that user separately receives the applicable event role and assignment.

### Privileged Account Provisioning

1. An authorized Admin enters the person's name and unique institutional email.
2. The system creates an active account without exposing a reusable initial password; privileged sign-in remains unavailable until the selected setup and verification requirements are complete.
3. The system sends a time-limited password setup or reset link through the configured institutional mail channel.
4. The Admin grants event roles and, for a Judge or Tabulator, explicit scoring assignments.
5. Every creation, role grant, and assignment grant is audited separately.

No route, link, or API permits anonymous registration.

### Verification

The repository must use one coherent verification policy; it must not protect routes with `verified` while the User model cannot satisfy that middleware.

Recommended path: treat possession of the Admin-invited institutional email through the time-limited password-setup flow as verification, store `email_verified_at`, and require verified email on privileged authenticated routes. If standard Laravel email verification is retained, the User model must implement the required contract and resend/verification routes must remain closed to already provisioned accounts. CSPC must confirm whether email delivery is reliable enough to make this mandatory.

### Disablement and Revocation

1. An authorized Admin disables an account or revokes a role or assignment with a reason.
2. Account disablement revokes all server-side sessions and persistent login tokens for that user.
3. Role revocation invalidates authorization for the event immediately.
4. Assignment revocation invalidates access to its scope immediately.
5. A currently open page may remain visible in the browser, but its next read or mutation is denied.
6. Queued offline commands are reauthorized during synchronization and rejected when the account, role, or assignment is no longer active.
7. Historical records retain the user's identity and the revoked membership or assignment history.

### Session Management

- Laravel database-backed sessions remain authoritative.
- Login rotates the session identifier; logout invalidates the session and regenerates the CSRF token.
- Password reset, account disablement, and an Admin `revoke all sessions` action invalidate all sessions for that user and rotate persistent-login credentials.
- Authorization is checked on every request; session existence never freezes an old role or assignment decision.
- Session lifetime, idle timeout, and whether Admins require a shorter timeout are open institutional decisions.

### Profile Actions

The initial authenticated profile exposes only profile editing, password change, and logout. The self-service hard-delete control and route must be removed; account disablement remains an authorized administrative action and preserves historical references.

## Domain/Data Requirements

Minimum data direction:

| Concept | Required data |
|---|---|
| User | Stable ID, name, normalized unique email, password hash, email verification timestamp, account state, disable reason/timestamp/actor, timestamps |
| Platform capability grant | User, capability enum containing `event_creator`, active/revoked state, granted/revoked actor and timestamps, reason |
| Event user role | Event, user, role enum, active/revoked state, granted/revoked actor and timestamps, reason |
| Scoring assignment | Event, user, scope enum, exactly one division/contest/entry-scorecard target, active/revoked state, granted/revoked actor and timestamps, reason |
| Session | Laravel database session ID, user ID, activity metadata, expiry data |
| Audit log | Actor, event, action, target type/ID, timestamp, request context, before/after security-relevant values, reason |

Database constraints must prevent duplicate active capability grants, duplicate active role memberships, and duplicate active assignments for the same user and exact scope. Constraints and transactional locking must preserve at least one active event creator. Historical grants and revocations must remain queryable.

### Inertia Authentication DTO

Authenticated Inertia responses must receive an explicit shared DTO, not the User model or unrestricted relationships:

```text
auth: {
  user: {
    id: string,
    name: string,
    email: string,
    email_verified: boolean
  },
  active_event: {
    id: string,
    name: string,
    roles: ["admin" | "judge" | "tabulator"]
  } | null,
  platform_capabilities: ["event_creator"],
  capabilities: [string]
}
```

`platform_capabilities` and `capabilities` contain only server-derived interface hints, with platform and active-event powers kept separate. They are not an authorization boundary and must not include broad assignment records, password fields, remember tokens, private participant data, or internal audit metadata. Assignment-scoped pages receive only the authorized target records needed by that page.

### Response and Cache Controls

- Authenticated Inertia documents, partial reloads, JSON scoring endpoints, and private downloads use `Cache-Control: private, no-store` and appropriate `Vary` headers.
- Authentication, password, session, and mutation responses are never stored by the service worker.
- Public sanitized routes use separate controllers/resources and must not derive cacheable payloads from private Inertia props.
- Authorization denials reveal no private target details.

## Invariants

- No anonymous user can create a privileged account.
- Only an active event creator can create an event or grant its first Admin, and event Admin membership does not imply that capability.
- At least one active event creator remains after bootstrap unless an approved recovery process is operating.
- An active account alone grants no event or scoring access.
- Public is never persisted as an event role.
- Event role and scoring assignment are separate records and separate checks.
- Judge or Tabulator scoring access requires both the matching active event role and active assignment.
- One Tabulator can have multiple assignments without gaining access outside those assignments.
- Assignment matching follows only the three defined scopes; narrow scopes do not inherit and overlapping grants remain independently effective.
- Disabled users and revoked memberships cannot authorize a new request or synchronized command.
- No user can hard-delete their own account, and the profile exposes only edit, password change, and logout actions.
- Historical security and scoring actor references survive disablement.
- UI capabilities never substitute for server-side policy checks.
- Authenticated responses are private and not stored in browser runtime caches by application policy.
- Identity, role, assignment, disablement, session-revocation, and approval actions are auditable.

## Edge and Failure Cases

- Duplicate email: normalize case and reject the second account without revealing account details to anonymous users.
- Invitation link expired or reused: reject it and let an authorized Admin issue a new setup link.
- Email delivery unavailable: retain the provisioned account safely and expose a controlled resend action; do not reveal or log plaintext credentials.
- Last active event creator disablement or capability revocation: block atomically unless another active event creator exists or an approved recovery process is operating.
- Event Admin attempts to create another event without `event_creator`: deny even if the user is Admin for every existing event.
- Event creator grants the first Admin for an event that already has one: reject the special first-Admin action and require the normal event Admin workflow.
- Role granted for an archived event: reject unless an explicit archive-access capability is introduced later.
- Assignment targets another event: reject at the domain boundary.
- Role revoked during form entry: reject submission and preserve only non-authoritative local form state.
- Session stolen or device lost: an Admin revokes all sessions, or the user changes their password through the retained profile action; all affected sessions fail on next use.
- Disabled user has queued offline commands: reject each command during server reauthorization; never apply it under the old identity state.
- Concurrent grant/revoke requests: serialize or constrain writes so only one active membership or assignment exists.
- Inertia validation or exception response: preserve `private, no-store` behavior and do not leak model attributes.

## Functional Requirements

| ID | Requirement |
|---|---|
| IDR-001 | The system shall expose no anonymous privileged-account registration action. |
| IDR-002 | The deployment shall provide an approved one-time method to create the first active event creator without a shipped default credential or implicit event role. |
| IDR-003 | An authorized Admin shall provision Judge, Tabulator, and additional Admin accounts through a closed workflow. |
| IDR-004 | The system shall enforce one coherent privileged-email verification policy and reject the current middleware/model mismatch. |
| IDR-005 | The system shall maintain active and disabled account states and deny disabled accounts. |
| IDR-006 | Account disablement, password reset, and explicit revocation shall invalidate applicable sessions and persistent-login credentials. |
| IDR-007 | The system shall remove self-service hard deletion, preserve referenced identities, and expose only profile edit, password change, and logout from the initial profile. |
| IDR-008 | The system shall store event role memberships separately from scoring assignments. |
| IDR-009 | The system shall permit one Tabulator to hold multiple explicit active assignments. |
| IDR-010 | The system shall deny every Judge or Tabulator read and mutation outside an active matching assignment. |
| IDR-011 | Laravel policies shall verify account, event role, assignment, target scope, and target state for each protected request. |
| IDR-012 | The server shall reauthorize queued commands at synchronization time. |
| IDR-013 | Inertia shall share only the explicit authentication DTO and server-derived capability hints defined here. |
| IDR-014 | Authenticated and private responses shall be marked private and no-store and excluded from service-worker runtime caching. |
| IDR-015 | The system shall audit provisioning, verification, role, assignment, account-state, session-revocation, and approval changes. |
| IDR-016 | Public read-only access shall require no account and shall create no role or assignment record. |
| IDR-017 | The result approval policy shall support the institution's eventual conflict-of-duty decision and an audited override if approved. |
| IDR-018 | The system shall store `event_creator` as a platform capability separate from event-scoped Admin membership. |
| IDR-019 | Only an active event creator shall create an event and grant that event's first Admin role; later event administration shall require the event role. |
| IDR-020 | The system shall block revocation or disablement that would leave no active event creator. |
| IDR-021 | Assignment scope shall be the closed enum `competition_division`, `contest`, or `entry_scorecard`, with the exact access matrix defined here and no `other` value. |
| IDR-022 | Overlapping active assignments shall be allowed, and access shall end only when no matching active assignment remains. |

## Acceptance Criteria

- The public registration route and UI are absent or return a non-success response.
- A deployment with no event creator can create exactly one initial active event creator through the approved bootstrap path, and rerunning it cannot create uncontrolled capability grants.
- An event creator can create a later event and grant its first Admin without automatically becoming that event's Admin or scorer.
- An event Admin without `event_creator` cannot create an event or grant the platform capability.
- Disabling or revoking the last active event creator fails; the same action succeeds after another active event creator exists.
- A provisioned user completes the selected verification/setup path without receiving a reusable plaintext password.
- A disabled account cannot log in, loses existing sessions, and remains visible as the actor on historical records.
- A Tabulator assigned to Basketball Men and Basketball Women can access both and is denied Volleyball in the same event.
- Removing the Basketball Women assignment immediately causes its next read and mutation to be denied while Basketball Men remains accessible.
- A division assignment reaches current and newly created direct contests in that division but not sibling divisions; contest and entry-scorecard assignments reach only their exact targets.
- When a user has overlapping division and contest assignments, revoking either one preserves exact-contest access through the other, and revoking both denies it.
- A user with a Tabulator role but no assignment receives an authorization denial.
- An assignment without the Tabulator or Judge event role grants no access.
- A queued command submitted after account, role, or assignment revocation is rejected.
- Shared Inertia props match the documented DTO and contain no password hash, remember token, unrestricted User model, or private relationships.
- Authenticated Inertia and scoring responses include private/no-store cache controls and are not returned from the service-worker cache.
- Role and assignment changes record actor, target, event, timestamp, reason, and before/after state.
- The profile offers edit, password change, and logout but no hard-delete action or route.
- Under the recommended conflict policy, an Admin cannot approve their own submitted result without the explicitly authorized override.

## Testing

### Domain and Feature Tests

- Closed provisioning and absence of public registration.
- First-event-creator bootstrap success, repeat protection, and no default credentials.
- Platform capability versus event Admin isolation, later event creation, first-Admin grant, and last-event-creator protection.
- Verification-required and verification-disabled configurations, according to the chosen policy.
- Active/disabled account transitions and session invalidation.
- Event isolation for Admin, Judge, and Tabulator roles.
- Multiple assignments for one Tabulator.
- Exact matching for every assignment scope, future direct contests under a division, no parent/sibling inheritance, overlap, and revoke fallback.
- Denial for targets outside every matching assignment in the same event.
- Role-only, assignment-only, revoked-role, and revoked-assignment denial.
- Concurrent duplicate role and assignment creation.
- Profile edit/password/logout availability and absence of self hard-delete.
- No-self-approval behavior and audited override if adopted.
- Exact Inertia DTO shape and sensitive-field absence.
- Audit completeness for every security-sensitive mutation.

### Security and Browser Tests

- CSRF, session fixation rotation, logout invalidation, password-reset revocation, and lost-device session revocation.
- Direct URL and forged-request authorization, not only hidden navigation.
- Private/no-store headers on success, validation, redirect, and error responses.
- Service worker never returns a prior user's authenticated Inertia response after logout or account switching.
- Authorization changes take effect in an already open browser tab.

## Decision Register

Identity and governance blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 1-8. This specification remains the technical contract for the authorization behavior after those decisions are recorded.
