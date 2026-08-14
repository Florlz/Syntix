# Syntix project context

This is the durable orientation document for agents working in Syntix. It summarizes the product, architecture, domain rules, current admin IA, development conventions, and open work. Detailed decisions live in the referenced plans and specs.

## Product

Syntix is a Laravel/Inertia event-operations admin system for a multi-sport competition. Administrators configure events, sports, divisions, department team entries, participants, rosters, eligibility, staff, draws, schedules, results, and public publishing.

The admin UI should be calm and operational: clear next actions, restrained Syntix colors, plain copy, useful empty/error states, and preserved sport/division/department context. The old Syntix PRD is outdated and is not the source of truth.

## Stack and runtime

- Laravel 13, PHP 8.4 target, Eloquent, controller validation, action/service classes, policies, audit logging, and Inertia.
- React 18, Inertia React, Vite, Tailwind CSS, Ziggy routes, and selected Headless UI primitives.
- SQLite is used for the fast feature/unit suite.
- PostgreSQL contract tests use an ephemeral Docker Compose service with fresh migrations and database constraints.
- Docker Compose is the supported runtime for PHP, Node, and database commands. Host PHP may be incompatible.
- Authenticated admin responses are private/no-store. Participant contact/private fields must only be exposed through intentional private admin projections.

Verification commands:

```powershell
docker compose exec -T app php artisan test
docker compose exec -T vite npm run test:ui -- --run
docker compose exec -T vite npm run build
docker compose -f compose.yaml -f compose.contract.yaml --profile contract run --rm contract
```

## Domain model

```text
Event
|- EventDelegation (department/college)
|- Competition (sport)
|  |- Division
|  |  |- governing CompetitionRuleVersion
|  |  |- Entry (department team sheet)
|  |  |  |- RosterMember -> Participant
|  |  |  `- EligibilityRecord -> Participant
|  |  |- Contest / Tournament / Bracket
|  |  `- schedules / placements / discipline configuration
|- Participants (shared event-level people)
|- Event staff and scoring assignments
`- public programme, publication snapshots, results, audit history
```

Integrity rules:

- A Participant belongs to one Event and one EventDelegation, but may join multiple sports.
- RosterMember is Entry-specific history. Inactivate memberships instead of deleting them.
- EligibilityRecord is Entry + Participant scoped. Player eligibility applies to student_athlete and reserve; coaches do not receive player eligibility controls or count as pending player eligibility.
- An Entry is a department team sheet for one Division. Current active/draft/locked entries are unique per Division + Delegation.
- Participant profile edits are shared across sports. A sport roster screen must not deactivate the shared participant.
- Published draws/results and archived data remain readable. Mutation and championship-ledger changes must be rejected.
- The closed event state is separate from archived; do not change its meaning.

## Admin information architecture

```text
Overview
Sports & Events
  |- Sports Directory
  |- Players & Rosters
  `- Schedules & Publishing
Event Staff
Results
```

Inside a selected sport:

```text
Overview
Players & Rosters
Matches & Brackets
Schedule & Publishing
Results
```

The workspace preserves tab, division, and roster department query parameters. Mutation redirects must preserve this context. The former top-level Tournament Desk was removed; tournament behavior remains under the selected sport/division as Matches & Brackets. Never silently select the first division.

## Important frontend files

- `resources/js/Layouts/AuthenticatedLayout.jsx` - responsive shell and Sports & Events submenu.
- `resources/js/Pages/Dashboard.jsx` - event overview and attention cards.
- `resources/js/Pages/Admin/Sports/Index.jsx` - compact sport directory.
- `resources/js/Pages/Admin/Sports/Workspace.jsx` - selected sport header, division context, tabs, and overview.
- `resources/js/Pages/Admin/Sports/Rosters.jsx` - roster management, readiness, lock/reopen, Players, Team Staff, History, Manage, and bulk eligibility.
- `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx` - Add Players drawer, quick-create, and CSV profile import.
- `resources/js/Pages/Admin/Sports/RosterPlayerList.jsx` - canonical participant rows and checkbox selection.
- `resources/js/Pages/Admin/Sports/Tournament.jsx` - nested draw/bracket workflow.
- `resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx` - event-wide participant directory.
- `resources/js/Pages/Admin/Approvals/Index.jsx` - result and final-standings review.
- `resources/js/Pages/Admin/Events/PublicProgramme.jsx` - schedule, venue, and publishing workflow.

## Important backend files

- `routes/web.php` - named admin and compatibility routes.
- `app/Http/Controllers/Admin/SportController.php` - sport workspace payload and containment.
- `app/Http/Controllers/Admin/RegistrationController.php` - participants, imports, roster membership, eligibility, atomic player management, and Entry status.
- `app/Services/RosterReadModel.php` - canonical roster DTO, counts, readiness, and capabilities.
- `app/Services/RosterReadiness.php` - lock blockers/notices.
- `app/Actions/Registrations/SaveRosterMembership.php` - single membership mutation and domain limits.
- `app/Actions/Registrations/SaveRosterMembershipBatch.php` - Add Players transaction.
- `app/Actions/Registrations/SaveRosterPlayer.php` - atomic profile + membership + eligibility Manage action.
- `app/Actions/Registrations/TransitionEntryStatus.php` - lock/reopen/withdraw/disqualify transitions.
- `app/Actions/Registrations/SetEligibility.php` and `SetEligibilityBatch.php` - eligibility decisions.
- `app/Actions/Registrations/CreateDepartmentRoster.php` and `SaveEntry.php` - team-sheet creation/update.
- `app/Support/EventOperationGuard.php` - shared archived/mutable-event guard.
- `app/Services/AuditLogger.php` and `app/Enums/AuditAction.php` - audit history.

## Open roster repair

The user reports that Add Players and Lock roster appear to do nothing. The approved repair is to review the complete roster mutation path and make Add Players, Lock/Reopen, Manage, bulk eligibility, quick-create, CSV import, Restore, locked/published correction, and archive failures visibly actionable without changing domain rules or route names.

Likely current silent paths:

- RosterAddPlayers uses a click handler and only displays memberForm.errors.members. Backend errors under entry, participant, role, or row fields can be invisible.
- The Add Players drawer lacks clear processing, success, and generic error feedback.
- Rosters disables Lock when readiness/capabilities are false but does not display statusForm.errors.entry/status/reason.
- The backend Lock action requires a governing rule, minimum active athlete count, valid player eligibility, role limits, active participants, and no blocking Entry state.

Add tests for success/failure, stale and cross-department IDs, limits, readiness, lock/reopen, and archived behavior. Preserve existing routes and redirect context.

## Existing hardening

The repository already includes bounded enum/model casts for tournament, discipline, bracket, advancement, and slot state; frozen historical migration lists; additive PostgreSQL checks with invalid-value preflight; corrected scoring-assignment matching; shared archive guards; and removal of unused alias model/policy names.

See:

- `docs/superpowers/plans/2026-08-13-roster-integrity-backend-hardening.md`
- `docs/superpowers/plans/2026-08-13-admin-sports-hub.md`
- `docs/superpowers/plans/2026-08-13-admin-roster-workflow.md`
- `docs/superpowers/specs/2026-08-13-admin-sports-hub-design.md`
- `docs/superpowers/specs/2026-08-13-admin-roster-workflow-design.md`
- `docs/superpowers/specs/2026-08-13-sport-workspace-spacing-design.md`
- `docs/syntix-roster-add-lock-handoff-2026-08-13.md`

Do not edit old migrations to chase future enum values. Add an additive migration with literal values and a preflight for genuinely new values. Keep canonical models and existing table/column names.

## Testing map

- `tests/Feature/Admin/RegistrationDeskTest.php` - registration, roster limits, eligibility, lock/unlock, publication protections, containment.
- `tests/Feature/Admin/RosterIntegrityTest.php` - canonical DTO, coach exclusion, adverse restore, archive rejection, locked correction.
- `tests/Feature/Admin/ParticipantCsvImportTest.php` - CSV parsing, duplicates, and roster add behavior.
- `tests/Feature/Backend/PostgresContractTest.php` - model casts and PostgreSQL checks.
- `tests/ui/RosterPlayerList.test.jsx` - current participant identity and filtered-empty coverage; expand for Add Players and Lock feedback.

Last known baseline: Laravel 118 passed, 2 skipped, 1,069 assertions; UI 2 passed; Vite build passed; PostgreSQL contract 2 passed with 7 assertions. Re-run after roster repair changes.

## Working conventions

- Preserve unrelated dirty worktree changes. Never use destructive reset/checkout commands.
- Use apply_patch for code and documentation edits.
- Keep public history, audit rows, brackets, results, and published snapshots intact.
- Use server-derived capabilities instead of recreating backend rules in React.
- Keep participant PII private and never expose storage paths or sensitive document data.
- The root `AGENTS.md` defines the Sol/Luna orchestration model. Architectural decisions belong to the orchestrator; implementation tasks should have explicit file boundaries and acceptance criteria.

## Deferred scope

- Participant medical permits and document uploads are intentionally deferred; no participant-document model/storage workflow exists yet.
- New judged-scorecard or athletics approval UI is not part of the current admin cleanup.
- Do not delete records or audit history.
