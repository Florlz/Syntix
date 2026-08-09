# SYNTIX System Implementation Plan

**Status:** Tasks 1–7 implemented and verified; Registration Desk planned

**Goal:** Deliver a proposal-backed SIKLAB administration system with one
platform-wide Global Admin, department-based teams, event-scoped Judge and
Tabulator access, automatic randomized tournaments, automatic result
progression, and auditable department championship points.

**Architecture:** Laravel and PostgreSQL remain authoritative for identity,
Event configuration, randomized draws, scores, official outcomes, placements,
and ledger totals. The proposal is imported through an idempotent application
action into the existing Event → Competition → Division domain; activity-
specific score payloads remain validated at the service boundary. Inertia React
renders one Event-selected Global Admin command center while Judge and
Tabulator workspaces remain assignment-scoped.

**Tech Stack:** Laravel 13, PHP 8.3+, PostgreSQL 17, Eloquent, Inertia 2,
React 18, Tailwind CSS 3, Vite 8, PHPUnit 12, Laravel sessions, and the existing
PWA shell.

This is the sole system implementation plan. Product requirements are in
[the single PRD](../prd/2026-08-09-syntix-product-prd.md).

## Global Constraints

- Exactly one active Global Admin exists for the installation. Event Admin and
  `event_creator` are legacy data only and authorize no request.
- Judge and Tabulator are the only active Event Roles. Scoring requires both
  the role and an active assignment whose target contains the scoring record.
- The seven proposal teams are Organizational Units represented by one
  Delegation per Event: Buhi, CAS, CCS, CHS, CEA, CTDE, and CTHBM.
- A team Division has at most one current Entry per Event Delegation.
- Participant, Entry, roster, and eligibility mutations are Global-Admin-only,
  Event-contained, audited, and non-destructive after they influence official
  records.
- Random drawing uses a cryptographically secure seed and a versioned,
  deterministic shuffle. The saved order is private before publication,
  reproducible for audit, and immutable after publication.
- An explicit redraw creates a separately audited preview before publication;
  command replay never creates a second draw.
- BYEs are automatic resolutions, not contests, wins, losses, forfeits, or
  points.
- Only approved Official Contest Outcomes advance brackets or change derived
  win/loss/draw standings.
- Contest points, sets, rubbers, rounds, wins, losses, draws, and Division
  Sub-Points never enter the championship ledger directly.
- Only Global Admin approval of a final Division Placement creates signed
  Major, Standard, Individual, or Intermediate Championship Points.
- Source defects remain visible blockers. Do not repair weights, roster limits,
  formats, tie rules, or participation eligibility by guesswork.
- Public and Inertia DTOs are explicit allow-lists and never expose passwords,
  draw seeds, private rosters, Judge drafts, peer scores, assignments, audit
  metadata, or unpublished previews.
- Preserve unrelated user work. Use additive migrations and do not rewrite
  historical official records.
- Do not commit unless explicitly requested.

## Approved Admin Interface Direction

- **Subject and job:** a CSPC SIKLAB operations desk for the sole Global Admin;
  its single job is to select an Event and make it operational.
- **Palette:** CSPC Navy `#0B2E4F` for authority, CSPC Gold `#D5A21F` for
  active actions, Paper `#F4F1E8` for the work surface, Ink `#17212B` for
  text, Success `#177245`, and Warning `#B45309`.
- **Typography:** Georgia/system serif for the Event identity, system sans for
  body and controls, and tabular system numerals for scores and totals.
- **Desktop:** Event selector and readiness rail first; Overview,
  Registrations, Programme, People & Access, Tournaments, Approvals,
  Publishing, and Reports form the operational navigation.
- **Mobile:** Event selector first, then stacked programme rows, compact
  criteria disclosures, assignment forms, and full-width tournament actions.
- **Signature element:** the SIKLAB readiness rail
  `Event → Programme → Registrations → Assignments → Draws → Live → Official`.
- **Interaction:** no decorative motion. State changes use short opacity or
  border transitions and respect `prefers-reduced-motion`. Redraw and publish
  require explicit confirmation.
- **States:** loading, empty Event, unapplied programme, blocked source,
  incomplete assignments, no eligible entries, generating, preview,
  published, stale revision, unauthorized, archived, and server error.

## Delivered Baseline

- The system contains Event, Delegation, Competition, Division,
  Entry, rule version, criterion, result, placement, ledger, Tournament,
  bracket-node, role, assignment, and audit persistence.
- Single-elimination, double-elimination, and round-robin generators exist,
  publication and approved-outcome advancement.
- Judge scorecards, Tabulator commands, final-placement approval, ledger
  effects, public brackets, public programme publication, and signed
  championship totals have focused test coverage.
- The delivered slice adds the sole Global Admin invariant, connected
  role/assignment provisioning, proposal Event configuration, automatic random
  draws, double-elimination routing, derived standings/placements, and the
  functional Admin command center described below.

## PRD Coverage

| Plan task | Primary PRD requirements |
|---|---|
| Task 1 | ID-001–ID-004, ID-007–ID-011, ADM-005 |
| Task 2 | SCR-001–SCR-011, JSC-002–JSC-004, ATH-001–ATH-004, ADM-008 |
| Task 3 | ID-005–ID-009, ID-012 |
| Task 4 | TBR-001–TBR-010, ADM-007 |
| Task 5 | SCR-005–SCR-013, BTR-001–BTR-004, SPT-001–SPT-002, JSC-001–JSC-004 |
| Task 6 | ADM-001–ADM-008, PUB-001–PUB-011, RPT-001–RPT-005 |
| Task 7 | OFF-001–OFF-005 and RT-001–RT-002 regression preservation plus all acceptance criteria |
| Task 8 | ADM-009–ADM-014, ID-013–ID-015, SCR-011, and the Registration and rosters acceptance criteria |

## Task 1: Enforce the sole Global Admin authority model

**Deliverable:** one immutable platform administrator path with legacy
administrative grants revoked and denied.

**Files:**

- Create: `database/migrations/2026_08_09_000016_enforce_global_admin_and_draw_invariants.php`
- Create: `app/Actions/Identity/BootstrapGlobalAdmin.php`
- Create: `app/Console/Commands/BootstrapGlobalAdminCommand.php`
- Modify: `app/Actions/Identity/BootstrapEventCreator.php`
- Modify: `app/Actions/Identity/DisableUser.php`
- Modify: `app/Actions/Events/CreateEvent.php`
- Modify: `app/Actions/Events/GrantEventRole.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Event.php`
- Modify: `app/Http/Controllers/Admin/EventController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/Pages/Admin/Events/Create.jsx`
- Modify: `database/seeders/DevelopmentAdminSeeder.php`
- Modify: `compose.yaml`
- Test: `tests/Feature/Identity/GlobalAdminTest.php`
- Test: `tests/Feature/Event/EventCreationWorkflowTest.php`
- Test: `tests/Feature/Event/EventRoleAuthorizationTest.php`

**Interfaces:**

- Consumes: `users.is_global_admin`, `AccountState`, Event policies, and
  existing audit/session services.
- Produces: `BootstrapGlobalAdmin::handle(array $attributes, ?string $reason):
  User`; Global-Admin-only `User::hasAdminAccess()`; local seeded credentials.
- Database/API changes: revoke active legacy Admin/event-creator grants; create
  a partial unique index where `is_global_admin = true`; add private draw
  reproducibility/idempotency columns used by Task 4.
- Authorization impact: all administration requires `isGlobalAdmin()`; the
  Global Admin still cannot score without a scorer role and assignment.

- [x] Add failing uniqueness, disablement, legacy-role denial, and Event
  creation tests.
- [x] Add the migration and transactional bootstrap guard.
- [x] Replace the event-creator/first-Event-Admin creation workflow.
- [x] Make local Docker setup idempotently seed the development administrator
  after migrations.
- [x] Run the focused identity and Event tests.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Identity/GlobalAdminTest.php tests/Feature/Event
```

**Expected result:** a second Global Admin is rejected, the sole account cannot
be disabled, legacy Admin capabilities authorize nothing, and
`admin@syntix.test` can administer every Event after local setup.

## Task 2: Import the proposal programme and department teams

**Deliverable:** one idempotent action that configures an Event from the
approved proposal and exposes source defects as scoped blockers.

**Files:**

- Create: `app/Support/Siklab2025Programme.php`
- Create: `app/Actions/Events/ApplySiklab2025Programme.php`
- Modify: `database/seeders/SiklabReferenceSeeder.php`
- Create: `database/seeders/DevelopmentAdminSeeder.php`
- Modify: `app/Http/Controllers/Admin/ConfigurationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Event/Siklab2025ProgrammeTest.php`

**Interfaces:**

- Consumes: the approved proposal, reusable point templates, existing Event,
  Delegation, Competition, Division, Entry, Discipline, rule-version, and
  criterion models.
- Produces: `ApplySiklab2025Programme::handle(User $actor, Event $event):
  Event`; an idempotent Programme Matrix projection.
- Database/API changes: no generic programme table; import normalizes the
  proposal into existing domain tables. Activity-specific rules use validated
  `scoring_configuration` only at the rule boundary.
- Authorization impact: only the Global Admin may apply or re-run the import.

- [x] Add golden-data tests for all seven teams, proposal competitions and
  sports, proposal formats,
  Men/Women divisions, point templates, Athletics disciplines, weight classes,
  and verified judged criteria.
- [x] Encode proposal data with source page/status on every rule and criterion.
- [x] Create all seven Event Delegations and one department Entry per team
  Division; keep nonparticipation/withdrawal explicit.
- [x] Seed verified rules as usable and contradictory rules as blocked.
- [x] Make the development Event receive the programme automatically.
- [x] Run import twice and verify no duplicate domain records.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Event/Siklab2025ProgrammeTest.php
```

**Expected result:** the Event contains the exact department/campus teams,
sports, formats, point schedules, and verified criteria from the proposal;
known defects are visible and affect only their own rules.

## Task 3: Provision Event-scoped Judges and Tabulators

**Deliverable:** one closed provisioning transaction that creates or reuses an
account, grants Judge or Tabulator for one Event, grants an exact assignment,
and returns one setup invitation when needed.

**Files:**

- Create: `app/Actions/Identity/ProvisionEventScorer.php`
- Modify: `app/Actions/Assignments/GrantScoringAssignment.php`
- Modify: `app/Actions/Events/GrantEventRole.php`
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/Admin/AccountController.php`
- Modify: `resources/js/Pages/Admin/Accounts/Create.jsx`
- Test: `tests/Feature/Identity/ProvisioningTest.php`
- Test: `tests/Feature/Event/ScoringAssignmentAuthorizationTest.php`

**Interfaces:**

- Consumes: `ProvisionUser`, `GrantEventRole`,
  `GrantScoringAssignment`, Event/Division/Contest/Scorecard containment.
- Produces: `ProvisionEventScorer::handle(User $actor, Event $event, array
  $attributes): array` with user, role, assignment, and optional invitation.
- Database/API changes: none.
- Authorization impact: role choices are only Judge or Tabulator; target Event
  and assignment containment are checked inside one transaction.

- [x] Add failing tests for partial provisioning, wrong-Event targets, invalid
  role/scope combinations, duplicate retries, and revocation.
- [x] Permit Division assignments for either scorer role while retaining role
  intersection at every scoring read/mutation.
- [x] Add role, assignment scope, and target controls to account provisioning.
- [x] Ensure setup URLs are returned only in the initiating private response.
- [x] Run provisioning and assignment authorization tests.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Identity/ProvisioningTest.php tests/Feature/Event/ScoringAssignmentAuthorizationTest.php
```

**Expected result:** no invited scorer can access an unrelated Event or target,
and no failed request leaves a role without its intended assignment.

## Task 4: Generate automatic randomized tournaments

**Deliverable:** one idempotent Global Admin command that securely shuffles all
eligible department Entries and generates a publishable tournament preview.

**Files:**

- Create: `app/Support/SeededDraw.php`
- Create: `app/Actions/Brackets/GenerateRandomTournament.php`
- Modify: `app/Http/Controllers/Admin/ConfigurationController.php`
- Modify: `app/Actions/Brackets/GenerateSingleEliminationBracket.php`
- Modify: `app/Actions/Brackets/GenerateRoundRobinSchedule.php`
- Modify: `app/Actions/Brackets/GenerateDoubleEliminationBracket.php`
- Modify: `app/Actions/Brackets/PublishBracket.php`
- Modify: `app/Models/DrawRecord.php`
- Modify: `app/Services/BracketAdvancer.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Brackets/RandomDrawTest.php`
- Test: `tests/Unit/Brackets/SingleEliminationGenerationTest.php`
- Test: `tests/Unit/Brackets/RoundRobinGenerationTest.php`
- Create: `tests/Feature/Brackets/RandomTournamentTest.php`

**Interfaces:**

- Consumes: a Division's governing format and active/locked Entries.
- Produces: `GenerateRandomTournament::handle(User $actor, Division $division,
  string $commandUuid): Tournament`; preview and publish routes.
- Database/API changes: encrypted random seed, algorithm version, and unique
  command UUID on `draw_records`; historical previews move to
  replaced/archived state on explicit redraw.
- Authorization impact: Global Admin only. Tabulators cannot draw, redraw,
  publish, reseed, or edit topology.

- [x] Write failing tests for secure non-fixed ordering, replay, redraw,
  publication lock, zero/one entry, seven-team BYE, and cross-Event denial.
- [x] Implement deterministic HMAC-SHA-256 ranking driven by encrypted
  `random_bytes()` seed material.
- [x] Route by governing format and preserve the existing single/round-robin
  algorithms.
- [x] Implement signed versioned 2-, 4-, and 8-slot double-elimination maps,
  lower-bracket BYE propagation, second-loss elimination, and reset final.
- [x] Synchronize populated bracket slots into Contest Entries at publication
  and advancement.
- [x] Run all bracket and Tournament Desk tests.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Unit/Brackets tests/Feature/Brackets/RandomTournamentTest.php
```

**Expected result:** seven eligible department teams appear exactly once in a
saved random order, one opening BYE is resolved automatically, and every
published route advances only from approved outcomes.

## Task 5: Derive sport outcomes, standings, and placement candidates

**Deliverable:** approved proposal-shaped score payloads automatically produce
winners, win/loss/draw records, bracket progression, standings, and final
placement candidates.

**Files:**

- Create: `app/Services/SportOutcomeResolver.php`
- Create: `app/Services/TournamentStandingCalculator.php`
- Create: `app/Services/AutomaticPlacementDeriver.php`
- Modify: `app/Actions/Scoring/CompleteContest.php`
- Modify: `app/Actions/Scoring/ApproveContestOutcome.php`
- Modify: `app/Http/Controllers/Tabulator/ContestController.php`
- Modify: `resources/js/Pages/Tabulator/Contest.jsx`
- Test: `tests/Feature/Scoring/AutomaticTournamentScoringTest.php`
- Test: `tests/Feature/Scoring/ResultApprovalTest.php`

**Interfaces:**

- Consumes: frozen `scoring_configuration.outcome_profile`, Contest Entries,
  submitted score payloads, and approved outcomes.
- Produces: normalized outcome payloads with winner/loser; derived
  win/loss/draw standings; a submitted final Division Placement when complete.
- Database/API changes: no denormalized standings table; standings are derived
  from approved outcomes. Existing placement and ledger tables remain the
  official boundary.
- Authorization impact: Tabulators submit only assigned scores; the server
  derives outcomes; only Global Admin approves outcomes and placements.

- [x] Add table-driven tests for team totals, sets, rubbers, rounds, Chess
  `1/0.5/0`, ties, incomplete series, and invalid Entry identifiers.
- [x] Normalize and validate proposal sport outcome profiles server-side.
- [x] Derive elimination and round-robin standings from approved outcomes.
- [x] Create final placement candidates only when all required official results
  exist and ties are resolved.
- [x] Keep Championship Points absent until separate placement approval.
- [x] Run focused scoring and ledger regression tests.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Scoring
```

**Expected result:** approved results automatically progress competitions and
produce placement candidates; approving those placements creates the proposal
points exactly once in each department's ledger.

## Task 6: Build the Global Admin command center

**Deliverable:** an Event-selected dashboard exposing programme, access,
tournament, approval, live, and department-standing operations.

**Files:**

- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `routes/web.php`
- Create: `tests/Feature/Admin/GlobalDashboardTest.php`
- Modify: `tests/Feature/Security/ResponseCacheTest.php`

**Interfaces:**

- Consumes: explicit Event route/query selection, Programme Matrix, role and
  assignment projections, Tournament previews, approval queues, live Contests,
  and `StandingCalculator`.
- Produces: an allow-listed Inertia DTO and forms for applying the programme,
  provisioning scorers, generating/redrawing/publishing tournaments, and
  navigating scoring/approval work.
- Database/API changes: none.
- Authorization impact: Global Admin receives the full command center; Judge
  and Tabulator receive only their assigned workspaces.

- [x] Add dashboard authorization, Event-switching, empty, blocked, and
  populated-state feature tests.
- [x] Return proposal sports/criteria/source status, all department teams,
  scorer assignments, tournament state, and signed totals without private
  payload leakage.
- [x] Implement the approved palette, typography, readiness rail, Programme
  Matrix, People & Access, and Tournament Desk.
- [x] Add confirmation for redraw/publish and explicit generating, empty,
  blocked, unauthorized, archived, and error states.
- [x] Verify keyboard focus, semantic headings, 44px targets, color-independent
  status, mobile stacking, tabular score data, and reduced motion.
- [x] Run dashboard/security tests and the production build.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Admin tests/Feature/Security
npm run build
```

**Expected result:** the sole Global Admin can select any Event and complete the
approved setup and tournament workflows from one responsive command center;
scorers and public users receive no private Admin data.

## Task 7: Regression, migration, and handoff verification

**Deliverable:** passing migration, automated regression, formatting, build,
and UI review evidence.

**Files:**

- Modify: this implementation plan only to mark completed checkboxes and record
  actual verification results.
- Test: all existing and newly created tests.

**Interfaces:**

- Consumes: Tasks 1–6.
- Produces: reproducible verification evidence and remaining blockers.
- Database/API changes: validate both fresh migration and upgrade migration.
- Authorization impact: regression-test every protected route for Global Admin,
  Judge, Tabulator, disabled user, legacy Admin, unrelated Event, and public.

- [x] Run focused tests after each task.
- [x] Run the complete suite.
- [x] Run Pint in fix mode, rerun the suite, and run `git diff --check`.
- [x] Build production assets.
- [x] Review the implemented frontend against the latest Web Interface
  Guidelines and report findings before any follow-up UI corrections.
- [x] Inspect the final diff for unrelated or destructive changes.

**Verification:**

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint
docker compose exec -T app php artisan test
npm run build
git diff --check
```

**Expected result:** all tests pass, formatting is clean, the production bundle
builds, the local Global Admin can sign in, the proposal programme is visible,
random brackets can be generated and published, and approved results update
department standings and Championship Points without manual arithmetic.

**Verification evidence (2026-08-09):** 89 tests and 686 assertions passed;
Pint checked 245 PHP files; the Vite production build and `git diff --check`
completed successfully.

## Task 8: Deliver the Global Admin Registration Desk

**Status:** Approved and pending implementation.

**Deliverable:** one Event-selected workspace where the sole Global Admin can
create and edit Participants, manage Division Entries and roster memberships,
record eligibility decisions, and make lifecycle-safe corrections before draws
or official records.

**Files:**

- Create: `database/migrations/2026_08_09_000017_add_registration_invariants.php`
- Create: `app/Actions/Registrations/SaveParticipant.php`
- Create: `app/Actions/Registrations/SaveRosterMembership.php`
- Create: `app/Actions/Registrations/SetEligibility.php`
- Create: `app/Actions/Registrations/TransitionEntryStatus.php`
- Create: `app/Http/Controllers/Admin/RegistrationController.php`
- Create: `app/Policies/ParticipantPolicy.php`
- Create: `app/Policies/EntryPolicy.php`
- Create: `app/Policies/EligibilityRecordPolicy.php`
- Create: `resources/js/Pages/Admin/Registrations/Index.jsx`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/RegistrationDeskTest.php`
- Modify: `tests/Feature/Admin/GlobalDashboardTest.php`
- Modify: `tests/Feature/Security/ResponseCacheTest.php`

**Interfaces:**

- Consumes: Event, Event Delegation, Participant, Division, Entry,
  Roster Member, Eligibility Record, governing roster limits, Entry state,
  Draw/publication state, Global Admin policies, and Audit Logger.
- Produces: an Event-contained Registration Desk DTO; create/update Participant,
  roster-membership, eligibility, and Entry-state actions; registration
  readiness counts for the dashboard.
- Database/API changes: enforce normalized non-null student-number uniqueness
  per Event and supporting current-roster indexes; add Event-nested GET/POST/
  PATCH/PUT routes with no destructive DELETE route.
- Authorization impact: only the active Global Admin may read or mutate the
  desk. Judge, Tabulator, disabled, unrelated, and anonymous users are denied.

- [ ] Add failing feature tests for Global Admin access, cross-Event denial,
  participant creation/edit/deactivation, normalized duplicate student number,
  private-field exclusion, and no student account creation.
- [ ] Add failing tests for Entry/Delegation containment, duplicate membership,
  Basketball's 15-athlete limit, coach-role limits, individual/pair/relay
  membership, eligibility states, required reasons, and transaction rollback.
- [ ] Add failing lifecycle tests for direct pre-lock editing, draw-readiness
  updates, locked Entry protection, pre-publication unlock/redraw requirements,
  and post-publication withdrawal/correction preservation.
- [ ] Add the additive uniqueness/index migration and transactional registration
  actions with audit records.
- [ ] Add Global-Admin-only policies, nested routes, explicit allow-listed DTOs,
  validation responses, and private/no-store cache behavior.
- [ ] Build the searchable Registration Desk with Delegation, Competition,
  Division, Entry mode, roster status, and eligibility filters in URL state.
- [ ] Build focused create/edit forms, roster and eligibility controls, inline
  blockers, loading/empty/error/stale states, mobile stacked records, keyboard
  focus, and explicit lifecycle-safe confirmations.
- [ ] Add registration readiness to the dashboard rail and operational
  navigation without exposing private roster data outside the desk.
- [ ] Run focused Admin/security tests, the full suite, Pint, production build,
  and `git diff --check`; then record the new verification evidence here.

**Verification:**

```bash
docker compose exec -T app php artisan test tests/Feature/Admin/RegistrationDeskTest.php tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Security/ResponseCacheTest.php
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
npm run build
git diff --check
```

**Expected result:** the sole Global Admin can register and correct Event
participants and rosters within proposal limits; all cross-Event, scorer,
public, duplicate, over-limit, locked, and history-destroying mutations are
denied without partial writes or private-data leakage.

## Deferred system roadmap

The approved implementation does not erase the existing broader roadmap.
Realtime transport, complete offline close/conflict UX, immutable queued report
artifacts, archive certification, and institutionally unresolved proposal rules
remain subsequent slices after this Admin/tournament foundation is verified.
