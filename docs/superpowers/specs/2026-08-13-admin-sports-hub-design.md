# Admin Sports Hub and Results Redesign

## Status

Approved design. This document is based on the current application behavior and the decisions made during the brainstorming and grilling session. The outdated Syntix PRD is intentionally not a source of requirements.

## Goal

Make the admin easy to scan and predictable to operate by making Sports & Events the hub for sport-level work, reducing sidebar destinations, and replacing the current raw Results Review queue with clear review workflows.

## Navigation model

The authenticated admin sidebar contains exactly:

1. Overview
2. Sports & Events
3. Event Staff
4. Results

Create Event and View Public Site remain utility links. Players & Rosters, Tournament Desk, and Schedule & Publishing are removed as top-level destinations; their capabilities remain available inside Sports & Events.

All routes that represent sport, division, discipline, registration, roster, bracket, schedule, or sport-result work must mark Sports & Events active. Results remains active for the cross-sport review/history inbox.

## Sports & Events hub

The root Sports & Events screen is a two-column desktop card directory and a one-column mobile list. Each card contains:

- Latest sport cover: draft cover for admin preview, then published cover, then a branded fallback.
- Cover state: Draft image, Public image, or No image.
- Sport name and Active/Inactive status.
- Active division count.
- Locked entries versus total entries.
- Earliest future schedule and its draft/public state.
- One highest-priority attention item, in this order: pending result review, pending eligibility or roster work, unpublished schedule changes, missing cover, no issues.
- One primary Manage sport action.

Add sport opens a modal. Sport identity, deactivation, reactivation, and division setup are behind Sport settings or the sport workspace rather than being repeated on every card.

The hub also exposes All schedules, which opens the event-wide schedule/venue view without restoring a sidebar item.

## Sport workspace

Manage sport opens a persistent sport shell with:

- Breadcrumb: Sports & Events / Sport name.
- Cover, status, entry/player summary, and a settings menu.
- URL-backed division selector for task tabs.
- Five tabs: Overview, Players & Rosters, Matches & Brackets, Schedule & Publishing, Results.

Overview can summarize all divisions. Every task tab requires an explicit division in the URL or a user selection; the app must never silently choose the first division.

### Players & Rosters

The workflow is entry-first:

`Sport → Division → Department entry → Roster`

Participant profiles remain event-global because one person can join multiple sports. The selected sport/division roster screen searches existing participants from the selected department, including people not yet assigned to this sport, before offering Create player. Removing a person changes only the sport roster membership. Editing a shared participant updates the shared profile and must be clearly labeled.

The screen shows roster limits, member roles, eligibility, entry status, lock state, and published-history restrictions together. Existing registration mutations and privacy headers remain authoritative.

### Matches & Brackets

The existing tournament functionality is nested in the sport shell and presented as Matches & Brackets. Generation, redraw, preview, publication, BYEs, discipline assignments, and public bracket behavior remain available. Internal route/model names may continue to use tournament terminology.

### Schedule & Publishing

The sport tab filters schedules, venues in use, and cover publication to the selected sport. All schedules provides the combined event view and shared venue management. Draft/public revision behavior, publication, withdrawal, auditing, and archived read-only behavior remain intact.

### Results

The sport tab reuses the same result review components as the global Results page, with the sport filter locked to the current sport.

## Results model and language

The global Results screen has two tabs:

- Needs review
- Official results

Filters include sport, division, and result type. Normal admin language uses four types:

1. Match result
2. Judged event
3. Measured discipline
4. Final standings

Raw payload keys, IDs, JSON, “ledger pending,” and similar implementation language are hidden under Technical details. Normal result DTOs resolve entry and department names and include the score/ranking summary, submitter, timestamp, revision, evidence, blockers, and available actions.

### Match results

Show named entries, score, winner/draw, submitter, time, and evidence. Review opens one focused panel with Confirm result or Return for correction.

Returning a result preserves the rejected submission, records a required reason, places the contest in the existing suspended state, and allows the assigned tabulator to edit, complete, and resubmit a new revision. The correction must not appear publicly as live.

### Judged events

Group submitted judge scorecards by contest. Admins compare entry totals and scorecards, may return an individual card with a reason, and can confirm only when all required assigned cards are submitted and the configured aggregation is valid. Confirmation approves cards and creates proposed Final standings; awarding championship points remains a separate action.

### Measured disciplines

Assigned tabulators receive a scoped entry screen for measurements and statuses. They save drafts and submit a discipline batch. Admins can return the batch or confirm it. Confirmation validates unit, precision, qualification, sorting direction, completeness, and tie state before creating discipline placements/sub-points and, when complete, proposed Final standings.

### Final standings

Show ranked departments and points with a plain-language action: Confirm standings & award points. This remains a separate, explicit, irreversible confirmation from match or discipline approval.

Official results preserve approval actor, time, revisions, correction history, and evidence.

## Compatibility and safety

- Preserve existing domain actions, audit records, public snapshots, bracket records, and authorization rules unless a correction workflow explicitly changes them.
- Preserve old named mutation routes.
- Old registration, tournament, programme, and approval GET URLs redirect or render the matching new scoped view with existing query context.
- Archived events remain visible and read-only.
- Do not duplicate participant records or hard-delete historical registrations/results.
- Generated mockup imagery is a visual reference only; application cards use existing uploaded cover records and a deterministic fallback.

## Acceptance criteria

- An admin can reach every sport operation from Sports & Events without Players & Rosters, Tournament Desk, or Schedule & Publishing sidebar items.
- The Sports & Events directory is readable at desktop widths without a three-column wall of repeated counters.
- A sport card communicates what needs attention without exposing raw IDs or implementation payloads.
- A roster admin can attach an existing event participant who has never been rostered in the selected sport.
- A task tab always retains sport and division context.
- Results review explains what is being approved and supports correction/resubmission for every supported result type.
- Existing scoring, bracket, schedule publication, cover publication, and history tests continue to pass.
