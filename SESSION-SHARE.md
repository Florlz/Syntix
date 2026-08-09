# SYNTIX Session Handoff

**Project:** SYNTIX / CSPC SIKLAB intramurals operations platform
**Workspace:** `/Users/diannemonte/Documents/FlorianCoding/syntix`
**Session date:** 2026-08-09
**Purpose:** Shareable summary of the product decisions, implementation work,
documentation cleanup, and remaining work from this session.

> This is a sanitized handoff summary, not a raw tool transcript. Development
> credentials are intentionally redacted.

## User goals and approved direction

- Use exactly one platform-wide Global Admin.
- Keep Judge and Tabulator accounts scoped to their Event, role, and exact
  scoring assignments.
- Follow the approved 2025 intramurals proposal for sports, department teams,
  judging criteria, point templates, and source blockers.
- Generate tournament brackets automatically using secure random draws.
- Automatically calculate outcomes, wins, losses, draws, standings, placements,
  and championship points through the appropriate approval boundaries.
- Make the development Admin frontend functional and dashboard-oriented.
- Give the Global Admin full operational control over Events, programme setup,
  teams, registrations, rosters, eligibility, staff access, tournaments,
  approvals, publishing, and reports where lifecycle rules permit.
- Keep one PRD and one system implementation plan as the only Markdown
  documentation authorities.

## Delivered implementation

### Identity and RBAC

- Enforced exactly one active Global Admin at the database and application
  layers.
- Retired Event Admin and `event_creator` authorization paths.
- Global Admin access is platform-wide and does not require Event membership.
- Judge and Tabulator authorization requires both the matching Event Role and
  an active exact Scoring Assignment.
- Assignment scopes are limited to Division, Contest, or Entry Scorecard.
- Provisioning is transactional and prevents partially authorized accounts.
- Development setup seeds the local Global Admin idempotently.

### Proposal programme

- Added the seven proposal department/campus teams:
  Buhi, CAS, CCS, CHS, CEA, CTDE, and CTHBM.
- Added proposal-backed competitions, divisions, rule versions, criteria,
  Athletics disciplines, point templates, and source references.
- Known proposal defects remain visible blockers instead of being guessed:
  Essay Writing, Cheer Dance, Dance Sports, Athletics conflicts, and roster or
  Esports source conflicts.

### Automatic tournaments

- Added cryptographically secure random Draw generation.
- Saved reproducibility material, algorithm version, command UUID, and Draw
  order.
- Added idempotent replay and audited pre-publication redraw.
- Added single-elimination, round-robin, and proposal-sized double-elimination
  routing.
- Added automatic BYE resolution without fake contests, wins, losses, or points.
- Published bracket topology is immutable.

### Scoring and standings

- The server derives winners and losers from validated sport scores rather than
  trusting client-provided winner fields.
- Added Chess `1 / 0.5 / 0` internal standings behavior.
- Added automatic tournament standings and final placement candidates.
- Match outcomes and internal Sub-Points do not directly create championship
  ledger entries.
- Separate Global Admin approval of a final Division Placement creates signed
  championship-point ledger effects exactly once.

### Admin frontend

- Regenerated the dashboard with CSPC navy, gold, paper, and ink styling.
- Added Event selection, readiness rail, Programme Matrix, People & Access,
  Tournament Desk, approvals, live contests, and department standings.
- Added functional random Draw, redraw, and publish controls with confirmation.
- Added responsive layout, URL-based dashboard tabs, keyboard focus, skip-link
  support, semantic labels, private responses, and mobile states.
- The dashboard error caused by a missing `draw_records.command_uuid` column was
  resolved by applying the pending migration to the running development
  database.

## Documentation consolidation

The former standalone ADRs, specifications, glossary, decision register, and
documentation index were merged into the PRD. Only these two Markdown
authorities remain under `docs/`:

- [`docs/prd/2026-08-09-syntix-product-prd.md`](docs/prd/2026-08-09-syntix-product-prd.md)
- [`docs/plans/2026-08-09-syntix-system.md`](docs/plans/2026-08-09-syntix-system.md)

The PRD now contains:

- Canonical terminology.
- Consolidated architecture decisions.
- Identity, scoring, tournament, Basketball, judging, Athletics, offline,
  public, report, archive, and frontend contracts.
- Registration, roster, and eligibility requirements.
- The cleaned institutional open-decision register.
- A disposition audit identifying stale or irrelevant former documents.

Outdated decisions explicitly discarded include Event Admin authority,
`event_creator` operational authority, manual organizer-entered Draws, the
claim that SYNTIX must never randomize, and obsolete visual-only frontend
sequencing.

## Current pending work

The next implementation slice is **Task 8: Global Admin Registration Desk** in
the system plan.

It is planned to provide:

- Private Participant creation and editing.
- Delegation, Competition, Division, and Entry filters.
- Team, individual, pair, relay, and mixed Entry management.
- Roster membership roles: student-athlete, reserve, student coach, and faculty
  coach.
- Eligibility states: pending, eligible, ineligible, withdrawn, and
  disqualified.
- Proposal roster-limit validation, including Basketball's 15-athlete limit.
- Lifecycle-safe editing before lock and explicit withdrawal, correction,
  unlock, or redraw workflows after lock/publication.
- Global-Admin-only authorization and audited non-destructive mutations.

Student self-service registration remains an open product decision and is not
enabled. The current approved direction is Admin-only registration; adding
student accounts would require a separate identity, verification, privacy,
duplicate, review, and rejection design.

## Verification evidence

The verified implementation baseline before the pending Registration Desk is:

- Laravel tests: **89 passed, 686 assertions**.
- Random tournament focused tests: **2 passed, 15 assertions** after the
  database migration repair.
- Laravel Pint: **245 PHP files passed**.
- Vite production build: passed.
- `git diff --check`: passed.
- Running development database: migration
  `2026_08_09_000016_enforce_global_admin_and_draw_invariants` applied.
- Running `draw_records` table now includes `command_uuid`, `random_seed`, and
  `algorithm_version`.

## Local development login

- Login URL: [http://localhost:8000/login](http://localhost:8000/login)
- Development email: `admin@syntix.test`
- Development password: **redacted in this share file**; retrieve it from the
  local setup documentation or development environment rather than sharing it
  with this handoff.

Change the development password before using the account outside a local
environment.

## Repository state

- Changes are uncommitted.
- Deleted documentation files are tracked and recoverable through Git history.
- No commit or remote publication was performed.
