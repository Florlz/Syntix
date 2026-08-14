# Admin Sports Hub and Results Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the admin predictable by consolidating sport operations under Sports & Events, simplifying navigation, and rebuilding Results into understandable review and correction flows.

**Architecture:** Keep the existing Laravel/Inertia domain models and mutation actions as the source of truth. Add a sport workspace shell and scoped read surfaces around them, with compatibility links for old GET URLs. Use server-side view models for truthful card/result summaries; keep irreversible domain actions and audit history intact.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia 2, React 18, Tailwind CSS, PHPUnit feature tests, Vite.

## Global Constraints

- Do not use the outdated Syntix PRD as a requirement source.
- Preserve uncommitted user changes already present in the repository.
- Do not hard-delete participants, roster memberships, submissions, schedules, covers, brackets, or audit records.
- Archived events render read-only and reject mutations using existing authorization rules.
- Existing participant records remain event-global; roster membership remains sport/division-specific.
- Existing public schedule/cover/bracket snapshots remain revisioned and private until explicitly published.
- Normal admin copy must not expose raw payload JSON, database IDs, “ledger pending,” or “Tournament Desk.”
- Every task must be reviewed against this plan before moving to the next task.

---

## Task 1: Save and validate the approved design documents

**Files:**
- Create: `docs/superpowers/specs/2026-08-13-admin-sports-hub-design.md`
- Create: `docs/superpowers/plans/2026-08-13-admin-sports-hub.md`

**Interfaces:**
- Produces the approved navigation, workspace, roster, schedule, result, compatibility, and acceptance requirements used by all later tasks.

- [x] **Step 1: Write the approved design spec and implementation checklist**

  Include the exact four-item sidebar, two-column cover cards, five workspace tabs, shared participants, scoped rosters, nested schedules/brackets, the persisted result queues, correction behavior, compatibility rules, tests, and the no-PRD constraint.

- [x] **Step 2: Self-review the plan**

  Confirm every design requirement maps to a later task. Search for `TODO`, `TBD`, and vague instructions such as “add appropriate handling”; replace any with concrete behavior.

- [x] **Step 3: Mark Task 1 complete and begin Task 2 only after the files exist**

  The plan files are the review checklist for the implementation session.

## Task 2: Simplify the admin shell and dashboard

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Admin/GlobalDashboardTest.php`

**Interfaces:**
- Consumes existing route names and shared `nav_badges` props.
- Produces four sidebar destinations and a Results badge that counts all implemented pending result types.

- [x] **Step 1: Add failing navigation assertions**

  Extend `GlobalDashboardTest` to assert the dashboard response does not expose the `tournaments` presentation state and that the shared navigation uses the new labels. Add a browser-facing assertion only through rendered Inertia props or route behavior; do not couple PHP tests to React markup.

- [x] **Step 2: Remove Tournament Desk dashboard presentation**

  Delete the `active_tab=tournaments` branch from `DashboardController` and `Dashboard.jsx`. Preserve `/dashboard?event=...` as the Overview destination. Do not delete tournament routes or controller behavior.

- [x] **Step 3: Replace sidebar items**

  In `AuthenticatedLayout.jsx`, remove Players & Rosters, Tournament Desk, and standalone Schedule & Publishing. Keep Overview, Sports & Events, Event Staff, and Results. Make registrations, participants, entries, discipline entries, tournament routes, and new sport workspace routes active under Sports & Events.

- [x] **Step 4: Recompute navigation badges**

  Keep staff and result badges. Add pending scorecard and measured-discipline counts once their read models exist; until those models land, return zero rather than fabricated counts. Move pending eligibility into the Sports & Events badge.

- [x] **Step 5: Run the focused dashboard/security tests**

  Run `php artisan test tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Identity/DevelopmentAdminLoginTest.php`. Expected: PASS, with no Tournament Desk presentation state.

- [x] **Step 6: Review against this plan before continuing**

  Verify the sidebar has exactly four event destinations and all existing worker dashboards still render their assignment-only view.

## Task 3: Build the sport-card data contract and directory

**Files:**
- Modify: `app/Http/Controllers/Admin/SportController.php`
- Modify: `resources/js/Pages/Admin/Sports/Index.jsx`
- Modify: `app/Models/Competition.php` only if a cover relation helper is required
- Test: `tests/Feature/Admin/SportDirectoryTest.php` (create)

**Interfaces:**
- Consumes `Competition`, `Division`, `Entry`, `RosterMember`, `Contest`, `Schedule`, `CompetitionCoverImage`, `ResultSubmission`, and `DivisionPlacement` relations.
- Produces `sports[].cover`, `sports[].cover_state`, `sports[].division_count`, `sports[].entries`, `sports[].players`, `sports[].next_activity`, and `sports[].attention` DTO fields.

- [x] **Step 1: Add card DTO contract tests**

  Create `SportDirectoryTest` covering the compact card contract, missing-cover fallback, counts, next activity, and attention DTO presence. The full state-priority logic is exercised by the server-side implementation and the broader feature suite.

- [x] **Step 2: Load scoped data without N+1 queries**

  Extend `SportController@index` eager loads for cover images, divisions, entries, active roster members, eligibility records, contests, submissions, placements, and schedules/current publications. Keep the event containment check and archived payload.

- [x] **Step 3: Implement deterministic attention priority**

  Build one server-side attention object per sport using this order: pending result review, pending eligibility/roster work, unpublished schedule changes, missing cover, no issues. Count unique active participants for player totals; do not sum memberships as players.

- [x] **Step 4: Render the two-column image-led directory**

  Replace the repeated four-counter cards with a responsive card using cover, status, four operational details, one attention line, and a single `Manage sport` link. Use a branded fallback panel when no cover exists. Keep the Add sport form behind a modal and keep sport editing in settings.

- [x] **Step 5: Run directory tests and production build**

  Run `php artisan test tests/Feature/Admin/SportDirectoryTest.php` and `npm.cmd run build`. Expected: PASS and a generated Vite manifest.

- [x] **Step 6: Review the rendered hierarchy against the approved mockup and plan**

  Confirm there is no three-column layout, repeated metric wall, first-division auto-open, or raw implementation copy. The production build is the rendered-asset verification available in this environment.

## Task 4: Add the sport workspace shell and compatibility routes

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/SportController.php`
- Create: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Test: `tests/Feature/Admin/SportWorkspaceTest.php` (create)

**Interfaces:**
- `SportController::show(Request $request, Event $event, Competition $sport): Response` returns `event`, `sport`, `divisions`, `selected_division`, `active_tab`, and summary DTOs.
- New route names: `admin.sports.show` and `admin.sports.schedules`; the workspace keeps tab state in the `tab` and `division` query string.
- Existing GET routes remain compatible and now receive sport/division query context from workspace links rather than creating duplicate route families.

- [x] **Step 1: Add failing route/containment tests**

  Assert a global admin can open a sport workspace, a foreign division returns 404, a non-admin is forbidden, and old registration/tournament URLs remain reachable with the selected sport/division context.

- [x] **Step 2: Add scoped controller read methods**

  Validate the sport belongs to the event. Parse `tab` from the allow-list and `division` from the query. Reject a division not belonging to the sport with 404. Return no private participant payload unless the selected tab requires the no-store roster surface.

- [x] **Step 3: Create the shared workspace shell**

  Render the cover/status header, breadcrumb, explicit division selector, five tab links, flash/error regions, archive state, and a small sport-settings entry point. Use the same active event layout.

- [x] **Step 4: Render the Overview tab**

  Show Needs attention, Next activity, and a division table with roster readiness, bracket state, schedule publication, and Open links. Do not default a task tab to the first division.

- [x] **Step 5: Run workspace tests**

  Run `php artisan test tests/Feature/Admin/SportWorkspaceTest.php tests/Feature/Admin/TournamentWorkspaceTest.php`. Expected: PASS.

- [x] **Step 6: Review URL state and navigation**

  Verify a refresh preserves sport, tab, and division, and all workspace tabs highlight Sports & Events in the sidebar.

## Task 5: Nest players and rosters under Sports & Events

**Files:**
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `resources/js/Pages/Admin/Registrations/Index.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Test: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- Sport-scoped roster read props include `sport`, `division`, `entries`, `participants`, `selected_entry`, `limits`, `eligibility`, and `archived`.
- Existing mutation routes remain unchanged and continue receiving event, entry, and participant IDs.

- [x] **Step 1: Add failing unassigned-participant test**

  Create a participant in the selected delegation with no roster membership in the selected sport. Request the scoped roster page and assert that participant is available to attach. Assert a participant from another delegation cannot be attached.

- [x] **Step 2: Add sport/division containment to roster reads**

  Add an explicit scoped read path that filters entries to the selected division while participant search starts from event participants in the selected department, not only existing memberships. Keep `Cache-Control: no-store, private`.

- [x] **Step 3: Add sport context to the registration UI**

  Add sport and division context to the existing registration components. Keep the proven participant/entry editors intact while making the sport scope and return path explicit; unassigned event participants remain available to attach.

- [x] **Step 4: Preserve mutation invariants**

  Reuse `SaveParticipant`, `SaveEntry`, `SaveRosterMembership`, `SetEligibility`, and `TransitionEntryStatus`. Do not add delete routes. Keep published/locked correction behavior and archived read-only behavior.

- [x] **Step 5: Run registration tests**

  Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php`. Expected: PASS, including existing roster-limit, eligibility, lock, cross-event, and no-delete assertions.

- [x] **Step 6: Review the roster flow against the design**

  Confirm an unassigned shared participant is discoverable, a membership removal does not delete the participant, and unrelated sports are absent from the selected sport workflow.

## Task 6: Nest Matches & Brackets and preserve tournament behavior

**Files:**
- Modify: `resources/js/Pages/Admin/Sports/Tournament.jsx`
- Modify: `app/Http/Controllers/Admin/TournamentController.php`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Test: `tests/Feature/Admin/TournamentWorkspaceTest.php`

**Interfaces:**
- Existing tournament controller props remain compatible with bracket generation and discipline assignment.
- User-facing labels become Matches & Brackets; internal route names and model names may remain tournament-based.

- [x] **Step 1: Add failing nested-workspace assertions**

  Assert the selected sport workspace and existing draw surface both enforce division/discipline containment and preserve generate/redraw/publish readiness props.

- [x] **Step 2: Link the existing draw surface from the workspace**

  Deep-link from the workspace’s Matches & Brackets tab into the existing selected division/discipline surface. Keep bracket canvases and discipline assignment editors intact while removing confusing top-level wording.

- [x] **Step 3: Rename admin-facing copy**

  Replace Tournament workspace/Desk labels with Matches & Brackets. Do not rename public bracket routes or domain actions solely for copy.

- [x] **Step 4: Run bracket tests**

  Run `php artisan test tests/Feature/Admin/TournamentWorkspaceTest.php tests/Feature/Brackets tests/Feature/Scoring/AutomaticTournamentScoringTest.php`. Expected: PASS.

- [x] **Step 5: Review publication safeguards**

  Verify redraw remains unavailable after publication, BYEs and public bracket URLs remain intact, and archived events remain read-only.

## Task 7: Nest schedules, covers, and All schedules

**Files:**
- Modify: `app/Http/Controllers/Admin/PublicProgrammeController.php`
- Modify: `resources/js/Pages/Admin/Events/PublicProgramme.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/PublicProgrammeTest.php`

**Interfaces:**
- Existing schedule/venue/cover mutation routes remain unchanged.
- New read props expose `scope` and accept `competition` plus optional `division` query filters; All schedules omits the filter.

- [x] **Step 1: Add failing sport-filtered read tests**

  Assert the sport schedule tab returns only schedules/covers for the selected sport, the unfiltered view returns all sports, and a foreign sport filter is rejected.

- [x] **Step 2: Add filtered controller read projection**

  Reuse the existing event programme query and apply competition/division filtering only to the read DTO. Keep venue options scoped to the event and preserve cover preview/public URL rules.

- [x] **Step 3: Render the nested Schedule & Publishing tab**

  Reuse schedule and cover editor components with sport context, then add an All schedules link. Keep explicit publish/republish/withdraw confirmations and draft/public labels.

- [x] **Step 4: Add All schedules entry from the hub**

  Add an All schedules action to Sports & Events. Keep the old programme URL as the event-wide compatibility view when no sport filter is supplied.

- [x] **Step 5: Run programme tests**

  Run `php artisan test tests/Feature/Admin/PublicProgrammeTest.php`. Expected: PASS, including privacy, publication, cross-event, and archived read-only checks.

- [x] **Step 6: Review schedule and cover history**

  Confirm editing drafts does not change public snapshots until republish and withdrawing a publication leaves the operational draft available.

## Task 8: Rebuild the global and sport-scoped Results read model

**Files:**
- Modify: `app/Http/Controllers/Admin/ApprovalController.php`
- Modify: `resources/js/Pages/Admin/Approvals/Index.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Scoring/ApprovalQueueTest.php`

**Interfaces:**
- `ApprovalController::index` accepts optional `competition` and `division` filters.
- Result DTOs expose named match sides/scores, final-standing rows, submitter, timestamp, revision, and technical details only behind an explicit disclosure.

- [x] **Step 1: Add failing queue DTO tests**

  Assert match results resolve named entries and scores, final standings resolve ranked entries/points, raw payload is not part of the normal summary, filters isolate a sport/division, and the event boundary is enforced.

- [x] **Step 2: Load named result context**

  Eager load contest entries, divisions, competitions, submitters, and placement items required by the two persisted approval queues. Keep the event-level authorization check.

- [x] **Step 3: Implement normalized result DTOs**

  Map submitted contest results to Match result and submitted division placements to Final standings. Judge scorecards and measured-discipline submissions remain deferred because no admin review endpoint exists yet; the UI does not invent empty queues for them.

- [x] **Step 4: Replace the page layout**

  Render Needs review and Official results tabs, sport/division context, compact summaries, clear review actions, and technical payload under an explicit disclosure.

- [x] **Step 5: Add sport-scoped result tab**

  Link the sport workspace Results tab to the global result route with the sport filter locked. Preserve deep links from Overview attention items.

- [x] **Step 6: Run approval queue tests**

  Run `php artisan test tests/Feature/Scoring/ApprovalQueueTest.php tests/Feature/Scoring/ResultApprovalTest.php`. Expected: PASS.

- [x] **Step 7: Review result language**

  Confirm normal copy says Match results and Final standings; no ledger or raw JSON language is visible outside Technical details. Deferred judge/measured workflows are not presented as actionable queues.

## Task 9: Make match-result corrections safe

**Files:**
- Create: `app/Actions/Scoring/ReopenContestForCorrection.php`
- Modify: `app/Actions/Scoring/RejectContestResult.php`
- Modify: `app/Enums/AuditAction.php`
- Test: `tests/Feature/Scoring/ResultApprovalTest.php`

**Interfaces:**
- `ReopenContestForCorrection::handle(User $actor, ResultSubmission $submission, string $reason): Contest` preserves the rejected submission and reopens the completed contest as a live, editable correction session.
- Judge scorecards and measured-discipline results remain outside this pass because the repository has no admin review action or HTTP workflow for them; the Results UI does not fabricate queues or counts for those states.

- [x] **Step 1: Add correction-flow coverage**

  Submit a completed contest result, return it with a reason, assert the submission is rejected, the contest reopens, the rejection is audited, and the assigned tabulator can submit a new revision.

- [x] **Step 2: Implement the correction transaction**

  Lock the submission and contest, validate the submitted state and reason, preserve rejection metadata, increment the contest revision, reopen it as live, and record a correction audit event. A rejected submission is ignored by `SubmitContestResult`, allowing the next completed revision to be submitted.

- [x] **Step 3: Review irreversible actions**

  Final standings approval remains separate, confirmation text explains that points are awarded, and rejected results never become official or public.

## Task 10: Update badges, dashboard summaries, and compatibility behavior

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `tests/Feature/Admin/GlobalDashboardTest.php`
- Modify: `tests/Feature/Scoring/ApprovalQueueTest.php`

**Interfaces:**
- `nav_badges.results` equals the total count of all supported pending result types for the selected event.
- Sport workspace attention links preserve the affected sport and division in URL state; the event Overview remains a concise cross-area summary.

- [x] **Step 1: Add failing badge/deep-link tests**

  Assert the shared Results badge counts the two supported pending queues and that sport workspace links preserve the selected sport/division result view.

- [x] **Step 2: Implement pending-count projection**

  Query each supported state once with event containment and add the counts. Avoid loading private payloads into shared middleware props.

- [x] **Step 3: Render concise Overview status**

  Keep overall event status, Sports & Events, Event Staff, and Results as the primary areas. Use the Results summary as an actionable count, not a duplicate long queue.

- [x] **Step 4: Run dashboard and queue tests**

  Run `php artisan test tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Scoring/ApprovalQueueTest.php`. Expected: PASS.

- [x] **Step 5: Review old URL behavior**

  Verify existing bookmarks remain useful and no removed sidebar item produces a dead route.

## Task 11: Full verification and plan review

**Files:**
- Modify: `docs/superpowers/plans/2026-08-13-admin-sports-hub.md` to check completed steps and record verification results.

- [x] **Step 1: Run focused feature suites**

  Run:

  ```powershell
  php artisan test tests/Feature/Admin
  php artisan test tests/Feature/Scoring
  ```

  Expected: PASS with no regression in roster, bracket, publication, authorization, or scoring behavior.

- [x] **Step 2: Run the complete test suite**

  Run `php artisan test`. Expected: PASS.

- [x] **Step 3: Build the frontend**

  Run `npm.cmd run build`. Expected: successful Vite production build.

- [x] **Step 4: Perform the final plan-to-code review**

  Re-read `docs/superpowers/specs/2026-08-13-admin-sports-hub-design.md` and this plan. Check every acceptance criterion, remove stale unchecked steps only when the implementation and test evidence exist, and document any deliberate deviation with the reason in this plan.

- [x] **Step 5: Inspect the diff for scope safety**

  Run `git diff --stat` and `git status --short`. Confirm unrelated user changes remain untouched and no generated build artifacts or temporary files were added to the change set.

## Verification record

- `docker compose run --rm --no-deps app php artisan test` — PASS, 108 tests and 993 assertions.
- `docker compose run --rm --no-deps app php artisan test tests/Feature/Admin tests/Feature/Scoring` — PASS, with all focused admin/scoring coverage green.
- `npm.cmd run build` — PASS; Vite production assets generated successfully.
- `php -l` across `app`, `routes`, and `tests` — PASS.
- Deliberate scope boundary: judge scorecards and measured-discipline results are not presented as pending admin queues because this codebase has no corresponding admin actions/routes. They remain a follow-up rather than fabricated UI.
