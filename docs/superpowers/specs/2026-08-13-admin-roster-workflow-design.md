# Admin Roster Workflow Design

**Date:** 2026-08-13

**Status:** Approved design awaiting written-spec review

**Source of truth:** The current Syntix codebase and the decisions recorded in this design session. The outdated product PRD is intentionally excluded.

## Purpose

Make roster work easy to understand during event operations. An admin must be able to enter a sport, choose a division, see every department's roster state, add or create players, resolve eligibility, and lock the roster without moving through unrelated event-wide forms.

The design also adds a small Sports & Events submenu and turns the event-level Players & Rosters destination into a department-grouped participant directory.

## Success criteria

- The primary path is Sport -> Division -> Department roster -> Players.
- Every active department is visible for a selected division, including departments whose roster has not been created.
- An admin can understand which department needs attention without opening every roster.
- Adding existing or new players never loses the selected sport, division, or department.
- Shared participant profiles are not duplicated per sport.
- Roster membership, eligibility, readiness, and lock state are understandable on one working surface.
- CSV import can accommodate common spreadsheet headings without silently guessing identity or overwriting profiles.
- The sidebar exposes relevant event-wide Sports & Events destinations without recreating Tournament Desk.
- Desktop, mobile, keyboard, archived-event, and private-data behavior are explicit.

## Information architecture

### Event-level sidebar

The primary navigation remains:

1. Overview
2. Sports & Events
3. Event Staff
4. Results

Sports & Events gains three event-level children:

- Sports Directory
- Players & Rosters
- Schedules & Publishing

The Sports & Events label links to Sports Directory. A separate caret toggles the submenu and exposes `aria-expanded` and `aria-controls`. The group expands automatically whenever a sport, registration, participant, roster, eligibility, bracket, discipline-entry, or schedule route is active. A manual toggle lasts for the mounted navigation session.

The pending-eligibility count appears on Players & Rosters. It may also cause the parent to show an attention indicator, but the same numeric count must not be duplicated. Event Staff and Results retain their own top-level counts.

Matches & Brackets remains inside a selected sport because it has no useful event-wide destination. Results remains top-level because it is the cross-sport review inbox; a sport's Results tab is only a scoped shortcut into it.

### Dashboard

The active-event dashboard continues to summarize three operational areas: Sports & Events, Event Staff, and Results. Sports & Events absorbs roster attention and links to the affected sport, division, or department roster when that context is known. Dashboard copy must describe the three displayed areas rather than referring to an obsolete five-area model.

### Sport workspace

The existing sport workspace remains the stable shell:

```text
Sports & Events
└── Basketball
    ├── Overview
    ├── Players & Rosters
    │   └── Men
    │       ├── CCS roster
    │       ├── CEA roster
    │       └── CAS roster
    ├── Matches & Brackets
    ├── Schedule & Publishing
    └── Results
```

Players & Rosters becomes real inline content instead of a launch panel that sends the admin to the legacy registration screen. The selected sport, tab, division, and department remain URL-backed:

```text
?tab=rosters&division={divisionId}&department={eventDelegationId}
```

A division must be selected explicitly. Department is the stable selection key because a Not started row has no Entry yet. The current Entry, when one exists, is derived from the validated division and department pair. On desktop, an absent valid `department` selection may display the first alphabetical department alongside the complete department index; the URL is then synchronized with that selection. On mobile, the department index is the first screen and selecting a department opens the roster drill-in view. Foreign event, sport, division, department, Entry, or participant combinations fail without exposing private data.

## Domain boundaries and terminology

- A Participant is an event-level identity belonging to one event department (`EventDelegation`). The same Participant may join multiple sports.
- An Entry is the department's roster container for one division. The interface calls it a department roster or team sheet; `Entry` remains an internal domain term.
- A RosterMember is one Participant's membership in one department roster.
- Eligibility remains specific to the Participant and Entry pair.
- Removing a person from one roster changes only that RosterMember. It does not delete or duplicate the shared Participant.

Existing participant, Entry, roster-membership, eligibility, and Entry-transition actions remain the authoritative mutation layer. A focused roster read model supplies the sport workspace with department summaries, selected-roster members, readiness blockers, and allowed actions. The event directory uses a separate read model but reuses the same participant mutation actions.

## Department roster workspace

### Department index

After the admin chooses a division, the left side shows every active department in stable alphabetical order. Each row contains:

- Department abbreviation and recognizable name
- Current player count and maximum, or a plain empty-state message
- One operational state
- One short explanation of the next concern

The operational states are:

- **Not started:** no current department roster exists for the division.
- **Review:** the roster exists but still has pending decisions or unmet requirements.
- **Ready:** the server's lock preflight reports no blockers, although optional places may remain.
- **Blocked:** a concrete rule or eligibility problem prevents locking.
- **Locked:** the roster is locked against ordinary edits.

The index uses an optional Needs attention filter. It never reorders departments based on state or recent activity.

### Selected roster

The selected roster occupies one continuous working surface and shows:

- Player count and configured limits
- Eligibility totals
- Required-role completeness
- Draft, active, or locked state
- Exact readiness blockers or confirmation that the roster is ready
- Player rows with identity, role, eligibility, and a Manage action
- One primary next action derived from current state

The primary action follows this map:

```text
Not started       -> Create roster
Draft/incomplete  -> Add players
Draft/ready       -> Review and lock roster
Locked            -> View roster / Reopen roster
Blocked           -> Resolve the named issue
```

Selecting Not started presents a short confirmation, creates the draft roster from the known event, sport, division, department, and governing participation mode, then opens it. It does not ask the admin to invent an Entry name, code, or mode. Server-side uniqueness and containment remain authoritative.

Review and lock roster opens one checklist of limits, required roles, eligibility, and publication-related blockers. The server reruns the preflight during submission. Reopening a locked roster is an explicit action with a required reason and audit record. Published tournament data continues to use the existing withdrawal, disqualification, or correction rules rather than silent edits.

## Adding and managing players

Add players opens a focused panel already scoped to the selected department roster. It supports this sequence:

1. Search existing event participants from the selected department, including people not assigned to the sport.
2. Select one or more people.
3. Review or change the role beside each selection. Student athlete is the default.
4. Create a missing participant without leaving the panel when necessary.
5. Review the exact selection.
6. Add everyone to the roster in one confirmed action.

Existing-person status is computed against the selected Entry only. The picker does not display misleading status derived from a membership in another sport.

The compact participant form fixes the department to the selected roster. It shows student number, display name, given name, family name, email, and phone; private notes and other optional information remain collapsed. Saving selects the new Participant immediately and keeps the Add players panel open.

The multi-add operation runs transactionally through existing roster rules. Department mismatches, roster limits, role limits, competition limits, locked state, or published-state restrictions cause the entire membership batch to roll back. The error identifies the affected person and corrective action.

New memberships begin with pending eligibility. An admin can select multiple pending players and apply one eligibility decision after reviewing the exact names. One reason and audit context apply to the batch. The server revalidates every record, and one stale or forbidden record rolls back the entire decision. Flagged and ineligible cases remain individually manageable.

Manage player opens a focused right-side panel on desktop and a full-screen panel on mobile. The panel states that identity and contact changes affect every sport. It returns focus to the invoking control when closed.

## Event participant directory

The event-level Players & Rosters submenu destination becomes a shared participant directory rather than a second roster builder.

- Participants are grouped into collapsible alphabetical department sections.
- Each section shows participant count and active roster-membership count.
- Within a department, people remain alphabetically ordered.
- Global search opens every department containing a match.
- Each person shows sport and division assignments as secondary information.
- Shared-profile creation and editing remain available.
- Entry controls, eligibility forms, and roster locking do not appear on this screen.

The directory continues to return private, non-cacheable responses.

## CSV import inside Add players

CSV import is available only inside a selected roster's Add players panel. It creates or reuses event participant profiles; it never adds memberships until the admin completes the separate final Add to roster action.

### Canonical template

The downloadable template contains:

```csv
student_number,display_name,given_name,family_name,email,phone,private_notes,active
```

`student_number` and `display_name` are required. Department data is intentionally absent because the selected Entry supplies the department and the server never trusts a CSV override.

### Guided mapping

The canonical template is preferred, but the upload screen provides limited guided mapping:

- Trimmed, case-insensitive canonical headings map automatically.
- Documented common headings such as `Student ID`, `ID Number`, and `Full Name` receive suggested mappings.
- An unmatched source column has a field dropdown and defaults to Do not import.
- A source column can map to at most one target, and a target can be supplied by at most one source column.
- Student number and display name must be resolved before validation.
- Mapping changes update the sample preview immediately.
- The system never guesses identity from name or email.

### Validation and confirmation

The file must be UTF-8, comma-delimited, no larger than 2 MB, and contain at most 1,000 nonblank data rows. UTF-8 BOM and CRLF files are accepted. Field limits and email/boolean rules match manual participant creation.

The preview classifies rows as:

- **New:** a new Participant will be created in the selected department.
- **Already exists:** the normalized student number belongs to an existing Participant in the same event and department; that person will be reused and preselected.
- **Conflict or error:** the row is invalid, duplicated within the file, or matches a Participant in another department.

One invalid row blocks all profile creation. The preview reports bounded row-numbered errors without echoing private notes or contact values. Existing profiles are never overwritten or silently moved.

After a clean preview, confirmation creates every new profile and its audit record in one transaction. Existing same-department profiles are reused. The imported and reused people return to Add players preselected, where the admin reviews per-person roles and performs the separate membership action.

The source CSV is never saved. The browser retains the selected File object during mapping and validation. The confirmation request sends normalized rows back to the server, which revalidates required fields, student-number normalization, department scope, duplicates, and current database state before writing.

## Visual direction

The roster workspace uses a sober competition team-sheet treatment rather than a generic dashboard-card system.

### Tokens

- Event navy: `#082944`
- Action teal: `#0B536D`
- Syntix gold: `#D5A21F`
- Work surface: `#F4F5F2`
- Primary text: `#17212B`
- Divider: `#CFD6D3`
- Muted text: `#68767E`

Department colors appear only as narrow identity strips in the department index. They never carry readiness meaning.

Typography uses restrained serif headings for sport and roster identity, Figtree for controls and prose, and Barlow Condensed for counts, limits, and operational labels. No new font dependency is required.

The page uses one working surface, firm dividers, and small corner radii reserved for controls. It avoids decorative gradients, oversized heroes, metric-card walls, floating card stacks, badge collections, and unexplained status dots. Status is always written in plain language.

The signature element is the department index, modeled after colored tabs on physical competition team sheets. The selected department becomes a solid navy row with a narrow gold edge.

Motion is limited to selection, submenu expansion, and focused-panel transitions. Reduced-motion preferences are respected.

## Responsive and accessible behavior

- Desktop uses a department index beside the selected roster.
- Mobile shows the department index first and opens a focused roster screen with an explicit Back to departments action.
- Add players and shared-profile editing use an accessible dialog/drawer implementation with focus trapping, Escape handling, focus restoration, and body-scroll management.
- Interactive targets remain at least 44 CSS pixels where practical.
- Active sidebar links and workspace tabs expose `aria-current`.
- Sidebar groups expose `aria-expanded` and `aria-controls`.
- Status meaning never relies on color alone.
- Validation summaries link or focus the first invalid control.
- Horizontal tab overflow remains keyboard reachable and visually discoverable.

## Error handling, privacy, and archived events

- Server-side containment validates event, sport, division, Entry, and Participant relationships on every read and mutation.
- Batch membership and eligibility actions are all-or-nothing transactions.
- Lock readiness is always recalculated at submission time.
- Mutations preserve the selected sport, division, roster, and open-panel context after validation failure or success.
- Archived events remain readable but disable roster creation, membership, eligibility, profile, CSV, lock, and reopen mutations.
- Participant pages and responses retain `Cache-Control: private, no-store`.
- Public pages receive no participant profile, CSV, selection, or roster-management metadata.
- Audit records describe controlled identifiers and state changes rather than private notes or contact fields.

## Verification strategy

Backend feature coverage must include:

- Event/sport/division/Entry/Participant containment
- Alphabetical department summaries and Needs attention filtering
- Missing-roster creation from known context and uniqueness under concurrency
- Same-department participant search, including unassigned people
- Exclusion of other departments
- Multi-add success, roles, limits, and complete rollback
- Pending eligibility and bulk-decision rollback
- Readiness preflight, lock, reason-required reopen, and published restrictions
- Shared profile behavior across multiple sports
- Event directory grouping and search
- Archived read-only behavior
- Authorization and private cache headers
- CSV file limits, encoding, mapped headers, required fields, duplicate handling, conflict handling, preview classifications, revalidation, and transaction rollback
- No retained source CSV and no unintended roster memberships during profile import

Navigation and frontend verification must include:

- Sports & Events parent and child active states
- Caret keyboard behavior and mobile drawer closure
- URL persistence for sport, division, roster, and tab
- Production asset build
- Desktop and mobile browser walkthroughs
- Keyboard-only roster management
- Dialog focus handling and visible focus
- Text-based state meaning and reduced-motion behavior

## Out of scope

- Participant document or medical-permit uploads
- Participant self-service accounts
- CSV import from the event-wide participant directory
- Automatic eligibility approval
- Changes to bracket, result-approval, or schedule behavior
- Replacement of the existing Participant identity model

Participant documents are a separate future design. They require private event-participant storage, document requirements, verification, expiry, immutable versions, download auditing, malware controls, and a retention policy before implementation.
