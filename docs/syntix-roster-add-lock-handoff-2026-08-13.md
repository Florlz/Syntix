# Syntix roster workflow handoff

## 2026-08-13 simplified roster and coach workflow

- Per-player eligibility is no longer part of the normal roster workflow. An active player membership means the player is cleared to compete.
- Locking now requires a single explicit roster/details review confirmation and stores an immutable `roster_approvals` snapshot with its revision, actor, players, coaches, and readiness state.
- Confirmed withdrawals and disqualifications are recorded as audited `participation_exceptions`; legacy eligibility records remain preserved for historical compatibility.
- Coaches and support people are shared, non-login participant profiles. They can be Student Coach or Faculty Coach, have an operational title, and cover either an exact division or a programme family.
- Coach capacity is maximum-only. Missing coaches produce warnings rather than lock blockers.
- The event directory now has separate Players and Coaches & support views, while each roster shows the coaches whose assignments cover that team sheet.
- Migration `2026_08_13_000004_simplify_roster_approval_and_add_coaches` was applied successfully to the local Docker database.
- Verification: Vite production build passed; focused RosterIntegrityTest (5 tests), RegistrationDeskTest (8 tests), and ParticipantCsvImportTest (4 tests) passed. The full Laravel suite reached 119 passing, 2 PostgreSQL skips, and one old CSV eligibility expectation; that expectation was then updated and its suite passed. Local Vitest could not run because `node_modules/.bin/vitest` is absent, but the production build completed.

## Suggested skills

- `brainstorming`  clarify the intended Add Players and Lock behavior before changing the workflow.
- `frontend-design`  keep the operational roster UI clear and make submit/error states visible without adding visual noise.
- `grilling`  stress-test roster state rules, especially readiness, locked/published entries, stale IDs, and archived events.

## Project

Syntix, a Laravel/Inertia admin system for event sports operations.

Workspace: `C:\Users\monte\Music\Coding\Syntix`

## Current user request

The admin roster screen still has non-functional-looking actions. The user reports that **Add Players does nothing** and **Lock roster does not work**. They approved a focused fix and asked for a full review of the roster workflow before implementation, without further clarification questions.

## Work already completed

The preceding implementation introduced:

- Canonical roster participant DTOs keyed by `id` and `display_name`.
- `PUT /admin/events/{event}/entries/{entry}/players/{participant}` (`admin.roster-players.update`) for atomic profile, membership, and eligibility updates.
- Separate Players, Team Staff, and collapsed Roster History sections.
- Server capabilities for editable, locked, published, blocked, and archived roster states.
- Batch roster membership and eligibility actions.
- Bounded state enums/model casts, archive guards, migration preflight/checks, and PostgreSQL contract tests.
- Vitest/RTL setup and roster list tests.

### Add Players / Lock repair completed

- Add Players is now submitted as a form, shows an in-place processing label, retains the drawer after success with confirmation, clears the selection, and renders every returned validation error.
- Lock and Reopen now clear stale errors before submission, show processing/error feedback, explain why locking is unavailable, and retain the selected sport/division/department after a successful state change.
- Batch membership now validates the locked entry and every selected participant's event/delegation before per-row work, returning a `members` validation error with no partial membership changes when a selection is stale.
- Verification on 2026-08-13: full Laravel suite passed (120 tests, 2 PostgreSQL-only skips, 1,080 assertions); Vitest UI suite (2 tests), Vite production build, and isolated PostgreSQL contract suite (2 tests, 7 assertions) all passed in Docker.
- Follow-up regression fix: opening a roster division without a selected department is guarded from accessing a null readiness payload, and the empty department state now uses a stable participant-list reference so it cannot enter a React update loop. Verified live in the browser and with the UI suite/build.

Detailed implementation plan and log:

- `docs/superpowers/plans/2026-08-13-roster-integrity-backend-hardening.md`

The worktree is intentionally dirty with many pre-existing and related changes. Preserve unrelated edits and do not reset or delete data.

## Most recent verification

Before the latest troubleshooting, container verification passed:

- Laravel: 118 passed, 2 skipped, 1,069 assertions.
- UI tests: 2 passed.
- Vite production build: passed.
- PostgreSQL contract suite: 2 passed, 7 assertions.

The SQLite additive combat-discipline migration was fixed after the first full run by changing the enum column through Laravel's SQLite schema grammar; PostgreSQL fresh migrations still pass.

Later diagnostic commands could not connect because Docker Desktop's daemon was unavailable. Recheck containers before testing.

## Relevant files and current behavior

### Add Players UI

`resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx`

- `available` filters event participants to the selected department whose selected-entry membership is not active and whose eligibility is not adverse.
- `addSelected()` builds `{ participant_id, role }` rows from the visible IDs and calls:

```js
memberForm.transform(() => ({ members })).put(
    route('admin.entry-members.batch', [event.id, entry.id]),
    { preserveScroll: true, onSuccess: () => { setSelected(new Set()); onClose(); } },
);
```

- The button is `type="button"` with `onClick={addSelected}` rather than a form submit.
- Only `memberForm.errors.members` is rendered. Errors returned under `entry`, `participant`, `role`, or a row key are invisible, making failed requests look like no-op clicks.
- The drawer closes only on Inertia `onSuccess`; there is no visible processing label, success message inside the drawer, or generic error fallback.
- The quick-create and CSV import controls are in the same drawer but the import response handling is minimal.

### Lock/Reopen UI

`resources/js/Pages/Admin/Sports/Rosters.jsx`

- `lock()` calls:

```js
statusForm.transform((data) => ({ ...data, status: 'locked', reason: '' }))
    .patch(route('admin.entries.status', [event.id, entry.id]), { preserveScroll: true });
```

- The Lock button is disabled when any of these are true: archived event, `selected.readiness.ready === false`, `statusForm.processing`, or `entry.capabilities.can_lock === false`.
- No `statusForm.errors` are rendered beside the button, so a backend validation failure appears to do nothing.
- The readiness component does show blockers, but the action itself does not explain why it is unavailable.
- Reopen uses the same status route with `status: 'active'` and currently requires a reason in the backend, but the UI's reopen confirmation must be checked for visible errors.

### Backend routes

`routes/web.php`

```text
PUT   /admin/events/{event}/entries/{entry}/members
      admin.entry-members.batch
PATCH /admin/events/{event}/entries/{entry}/status
      admin.entries.status
PUT   /admin/events/{event}/entries/{entry}/players/{participant}
      admin.roster-players.update
```

### Backend actions

- `app/Actions/Registrations/SaveRosterMembershipBatch.php` validates IDs, locks the Entry and selected Participants, then delegates each row to `SaveRosterMembership` in one transaction.
- `app/Actions/Registrations/SaveRosterMembership.php` enforces event/delegation containment, archive/lock/withdrawn/published restrictions, roster limits, competition limits, and creates pending eligibility only for new athletes/reserves.
- `app/Actions/Registrations/TransitionEntryStatus.php` validates archive/event containment, published restrictions, reasons for unlock/withdraw/disqualify, and calls `RosterReadiness` before allowing `locked`.
- `app/Services/RosterReadiness.php` requires a governing rule, minimum roster size, role limits, no pending/adverse player eligibility, active participants, and no blocking entry state.
- `app/Services/RosterReadModel.php` supplies `selected.entry`, `selected.participants`, counts, readiness, and server capabilities.

## Likely repair scope

1. Convert Add Players to an explicit form submission path with `processing` feedback, success feedback, and a generic error summary that renders all Inertia validation errors.
2. Keep the visible-ID/stale-ID filtering, but show server errors for stale, cross-department, locked, published, withdrawn, disqualified, limit, and competition-limit failures.
3. Make the batch action explicitly validate Entry/event/delegation containment before starting row operations and return a useful `members` error when a selected row is no longer addable.
4. Add a clear Lock submission state and render `statusForm.errors.entry`, `statusForm.errors.status`, `statusForm.errors.reason`, plus a generic fallback. Keep the button disabled only when the server capability/readiness genuinely forbids locking.
5. After successful lock/reopen/add, reload or preserve the selected division/department context so the user immediately sees the new state.
6. Review Manage, bulk eligibility, quick-create, CSV import, Restore, locked/published correction, and archived read-only flows for the same silent-error pattern.
7. Add Laravel feature tests for successful add, stale/cross-department failure, roster-limit failure, successful lock, blocked lock with visible validation contract, and archive rejection.
8. Add UI tests for click feedback, error rendering, selection clearing after success, lock-disabled copy, and preserving context after a response.

## Constraints

- Do not change route names or remove existing compatibility endpoints.
- Preserve all records, audit history, brackets, results, and published snapshots.
- Keep shared Participant profiles event-scoped; roster membership and eligibility remain Entry-scoped.
- Archived events remain readable but all mutations must be rejected.
- Do not reintroduce the outdated Syntix PRD.
- Participant medical/document uploads remain deferred.
- Use the running containers for PHP, UI tests, build, and PostgreSQL verification.

## Recommended verification commands

```powershell
docker compose exec -T app php artisan test
docker compose exec -T vite npm run test:ui -- --run
docker compose exec -T vite npm run build
docker compose -f compose.yaml -f compose.contract.yaml --profile contract run --rm contract
```

Also manually check in the browser:

- Add one player and multiple players.
- Attempt stale/cross-department selections and confirm a visible error.
- Lock a ready roster and a roster with pending eligibility.
- Reopen with and without a reason.
- Manage a player, restore history, apply bulk eligibility, and verify locked/published/archived behavior.
- Confirm keyboard focus and mobile drawer behavior.

## Latest conversation update

The user explicitly approved the focused Add Players/Lock repair and requested a full review of the related roster functionality without additional clarification questions. The next agent should proceed directly with implementation using the repair scope above.

The Add Players/Lock repair is complete; its behavior and verification are recorded above. The existing roster hardening changes and tests remain as referenced above.

The latest Docker diagnostic could not reach the Docker Desktop daemon. Check container availability before running verification; do not infer a code failure from that environment error.

## Project-wide context

### Product purpose

Syntix is an event-operations admin application for running a multi-sport competition. Administrators configure an event, organize sports and divisions, create department/team entries, register participants, manage rosters and eligibility, assign staff, generate draws, schedule matches, review results, and publish a public programme/scoreboard. The admin UI is intentionally operational and restrained rather than marketing-oriented.

### Stack and runtime

- Backend: Laravel 13, PHP 8.4 target, Eloquent, Form Requests/controller validation, actions/services, policies, audit logging, Inertia responses.
- Frontend: React 18, Inertia React, Vite, Tailwind CSS, Ziggy route helpers, Headless UI primitives where appropriate.
- Databases: fast SQLite feature/unit tests plus an ephemeral PostgreSQL contract service for fresh migrations and database check constraints.
- Runtime: Docker Compose services include the Laravel app, Vite frontend, and database services. Host PHP is not a reliable test runner because the project requires PHP 8.4.
- Private data: authenticated admin responses use private/no-store headers. Participant contact/private fields are only intentionally projected into private admin pages.

### Domain hierarchy

```text
Event
 EventDelegation (department/college)
 Competition (sport)
   Division
      governing CompetitionRuleVersion
      Entry (one department team sheet)
        RosterMember -> Participant
        EligibilityRecord -> Participant
      Contest / Tournament / Bracket
      schedules / placements / discipline configuration
 Participants (shared event-level people)
 Event staff and scoring assignments
 public programme, publication snapshots, results, audit history
```

Important ownership rules:

- A `Participant` belongs to exactly one Event and EventDelegation, and may join multiple sports.
- `RosterMember` is the Entry-specific membership/history link. It is retained and inactivated rather than deleted.
- `EligibilityRecord` is Entry + Participant scoped. Eligibility controls apply to `student_athlete` and `reserve`; coach history may exist but does not block player readiness or count as pending player eligibility.
- An Entry is the department/team roster container for one Division. Active current entries are unique per Division + Delegation.
- Participant profile edits are shared across sports. Sport-scoped screens must not deactivate the shared participant.
- Published draws/results and archived-event data remain readable; mutation and ledger changes are blocked.

### Current admin information architecture

The old flat navigation was simplified. The current intended shell is:

```text
Overview
Sports & Events
   Sports Directory
   Players & Rosters (event directory; sport pages deep-link into a selected division/department)
   Schedules & Publishing
Event Staff
Results
```

Inside a selected sport, URL-backed context is retained through the workspace tabs:

```text
Overview
Players & Rosters
Matches & Brackets
Schedule & Publishing
Results
```

`Tournament Desk`, the duplicate top-level destination, was removed from the shell. Tournament functionality still exists under the selected sport/division as Matches & Brackets. Do not restore the old label or silently choose the first division.

The sport workspace uses `tab`, `division`, and (for rosters) `department` query parameters. Preserve these when redirecting after mutations so the administrator remains in context.

### Key frontend surfaces

- `resources/js/Layouts/AuthenticatedLayout.jsx`  responsive admin shell/sidebar and Sports & Events submenu.
- `resources/js/Pages/Dashboard.jsx`  event overview with sports/staff/results attention cards.
- `resources/js/Pages/Admin/Sports/Index.jsx`  compact sport directory.
- `resources/js/Pages/Admin/Sports/Workspace.jsx`  sport header, division context, workspace tabs, overview panels.
- `resources/js/Pages/Admin/Sports/Rosters.jsx`  department roster screen, readiness, lock/reopen, player/staff/history sections, Manage panel, bulk eligibility.
- `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx`  Add Players drawer, quick-create, CSV profile import.
- `resources/js/Pages/Admin/Sports/RosterPlayerList.jsx`  canonical participant rows and checkbox selection.
- `resources/js/Pages/Admin/Sports/Tournament.jsx`  nested draw/bracket workflow.
- `resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx` and `ParticipantProfileForm.jsx`  event-wide participant directory/profile editing.
- `resources/js/Pages/Admin/Approvals/Index.jsx`  results/final standings review.
- `resources/js/Pages/Admin/Events/PublicProgramme.jsx`  schedule/venue/publication workflow.

### Key backend surfaces

- `routes/web.php`  named admin routes and compatibility endpoints.
- `app/Http/Controllers/Admin/SportController.php`  sport directory/workspace payload and division/department containment.
- `app/Http/Controllers/Admin/RegistrationController.php`  participant directory, imports, roster membership, eligibility, atomic player update, and Entry status transitions.
- `app/Services/RosterReadModel.php`  server-authoritative roster DTO, counts, readiness, and capabilities.
- `app/Services/RosterReadiness.php`  lock blockers and notices.
- `app/Actions/Registrations/SaveRosterMembership.php`  single membership mutation and domain limits.
- `app/Actions/Registrations/SaveRosterMembershipBatch.php`  atomic Add Players batch mutation.
- `app/Actions/Registrations/SaveRosterPlayer.php`  atomic profile + membership + eligibility Manage mutation.
- `app/Actions/Registrations/TransitionEntryStatus.php`  lock/reopen/withdraw/disqualify transition rules.
- `app/Actions/Registrations/SetEligibility.php` and `SetEligibilityBatch.php`  individual/bulk eligibility decisions.
- `app/Actions/Registrations/CreateDepartmentRoster.php` and `SaveEntry.php`  Entry/team-sheet creation and updates.
- `app/Support/EventOperationGuard.php`  shared archive/mutable-event guard.
- `app/Services/AuditLogger.php` and `app/Enums/AuditAction.php`  immutable operational history.

### Backend hardening already in the project

The previous hardening pass added bounded enums and model casts for tournament/discipline/bracket state, literal frozen historical migration lists, additive PostgreSQL checks with preflight invalid-value detection, scoring-assignment target matching, archive guards, and removal of unused alias model/policy names. The authoritative details and file-level log are in:

- `docs/superpowers/plans/2026-08-13-roster-integrity-backend-hardening.md`

Do not rewrite old migrations to chase future enum values. Add a new additive migration with an explicit preflight when a value is genuinely added. Keep canonical models (`Division`, `RosterMember`, `EntryScorecard`) and existing table/column names.

### Other project artifacts

Use these instead of reconstructing historical decisions from chat:

- `docs/superpowers/specs/2026-08-13-admin-sports-hub-design.md`  sport directory/workspace design.
- `docs/superpowers/plans/2026-08-13-admin-sports-hub.md`  sport hub implementation record.
- `docs/superpowers/specs/2026-08-13-admin-roster-workflow-design.md`  roster workflow design.
- `docs/superpowers/plans/2026-08-13-admin-roster-workflow.md`  earlier roster workflow implementation plan; do not overwrite it.
- `docs/superpowers/specs/2026-08-13-sport-workspace-spacing-design.md`  spacing/layout decisions.
- `docs/Approved-2025-Intramurals-Proposal.md`  seeded programme reference used by the current data/application setup, not an instruction to revive the outdated PRD.

The user explicitly said the old Syntix PRD is outdated and should not guide current work. Avoid relying on deleted `docs/prd`/`docs/plans` copies or the old top-level Tournament Desk concept.

### Test and verification map

- Feature tests live under `tests/Feature/Admin`, `tests/Feature/Event`, `tests/Feature/Scoring`, `tests/Feature/Public`, and related domains.
- `tests/Feature/Admin/RosterIntegrityTest.php` covers canonical roster DTO behavior, coach exclusion, adverse restore rules, archive rejection, and locked correction behavior.
- `tests/Feature/Admin/RegistrationDeskTest.php` covers registration, roster limits, eligibility, lock/unlock, publication protections, and participant containment.
- `tests/Feature/Admin/ParticipantCsvImportTest.php` covers import parsing/duplicate/roster-add behavior.
- `tests/Feature/Backend/PostgresContractTest.php` verifies model casts and fresh PostgreSQL constraints.
- `tests/ui/RosterPlayerList.test.jsx` currently covers participant identity/checkbox selection and filtered-empty rendering. It needs expansion for Add Players and Lock error/processing behavior.
- `vitest.config.js` and `tests/ui/setup.js` provide the container-friendly UI test environment.

Known successful baseline before the current bug repair: Laravel 118 passed, 2 skipped, 1,069 assertions; UI 2 passed; Vite build passed; PostgreSQL contract 2 passed with 7 assertions. Re-run after changes.

### Current worktree and collaboration rules

- The worktree is intentionally dirty and contains related prior redesign/hardening edits, deleted legacy docs/aliases, and untracked new files. Preserve unrelated user changes.
- Do not use destructive reset/checkout commands, delete database records, or commit unless explicitly asked.
- Use `apply_patch` for edits.
- Use Docker containers for PHP/Node verification; host PHP requires the wrong version.

### Deferred or explicitly out of scope

- Participant medical permits and document uploads: discussed, then intentionally deferred. There is no participant-document model/storage workflow yet.
- New judged-scorecard or athletics approval UI: not part of the current admin cleanup.
- No records, audit rows, brackets, result history, or public snapshots may be deleted.
