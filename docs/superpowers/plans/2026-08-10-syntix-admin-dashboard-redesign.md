# Syntix Admin Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Laravel-derived authenticated navigation with a responsive Syntix operations sidebar and surface immediate event information in clear dashboard and public-landing cards.

**Architecture:** Keep Laravel, Inertia, React, and Tailwind as-is. The shared authenticated layout owns the desktop sidebar and mobile drawer; the dashboard consumes its existing payload to render operational summaries without introducing new server data, while the public landing derives quick facts from its already-public event collections.

**Tech Stack:** Laravel 12, Inertia React, React 18, Tailwind CSS 3, Vite

## Global Constraints

- Preserve all existing route authorization and response payload boundaries.
- Do not add a frontend icon or component dependency.
- Keep every navigation control keyboard accessible and responsive.
- Preserve the existing CSPC navy (`#0B2E4F`) and SIKLAB gold (`#D5A21F`) identity.
- Do not modify unrelated working-tree changes.

---

### Task 1: Syntix brand mark and authenticated application shell

**Files:**
- Modify: `resources/js/Components/ApplicationLogo.jsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`

**Interfaces:**
- Consumes: `usePage().props.auth.user`, `auth.global_admin`, and `auth.active_event`.
- Produces: `AuthenticatedLayout({ header, children })` with the same public component contract used by all authenticated pages.

- [ ] **Step 1: Replace the framework-derived SVG paths**

Implement a compact, original Syntix cube/S monogram using stroked SVG geometry and `currentColor`, retaining passthrough props so existing size and color classes still work.

- [ ] **Step 2: Build the desktop sidebar**

Render Syntix identity, event context, route-aware links for Overview, Registrations, Programme, Approvals, People & Access, Tournament Desk, and Public Site, plus the signed-in account and logout action.

- [ ] **Step 3: Build the mobile shell**

Render a compact top bar, modal backdrop, close control, `aria-expanded`, and the same navigation links inside a slide-in drawer. Close the drawer after navigation.

- [ ] **Step 4: Preserve layout compatibility**

Keep the `header` slot and `#main-content` skip target so existing authenticated screens need no migration.

- [ ] **Step 5: Run the production build**

Run: `npm.cmd run build`
Expected: Vite completes without JSX or Tailwind compilation errors.

### Task 2: Admin dashboard information hierarchy

**Files:**
- Modify: `resources/js/Pages/Dashboard.jsx`
- Test: `tests/Feature/Admin/GlobalDashboardTest.php`

**Interfaces:**
- Consumes: existing `event`, `readiness`, `people`, `pending_approvals`, `live_contests`, `programme`, and `teams` props.
- Produces: an information-first Overview with summary cards, quick actions, readiness status, and existing programme/access/tournament work areas.

- [ ] **Step 1: Add dashboard summary cards**

Derive four cards from existing props: registration detail, active scorers, pending approvals, and live contests. Each card includes a plain-language status and an appropriate destination where one exists.

- [ ] **Step 2: Add a compact quick-actions block**

Link to Registration Desk, Public Programme, Approvals, worker provisioning, and event creation using existing authorized routes.

- [ ] **Step 3: Recompose the event header**

Replace the oversized decorative hero with a compact operations masthead containing event selection, lifecycle state, primary action, and the seven-stage readiness rail.

- [ ] **Step 4: Keep deep work areas intact**

Preserve Programme Matrix, People & Access, Tournament Desk, championship totals, and all mutation controls; only adjust their placement and visual hierarchy.

- [ ] **Step 5: Run focused feature coverage**

Run: `php artisan test tests/Feature/Admin/GlobalDashboardTest.php`
Expected: all dashboard payload and authorization assertions pass.

### Task 3: Public landing quick-information blocks

**Files:**
- Modify: `resources/js/Pages/Welcome.jsx`
- Test: `tests/Feature/Public/PublicLandingTest.php`

**Interfaces:**
- Consumes: existing public `featured_event`, `live_contests`, `featured_contest`, `competitions`, and `leaderboard` props.
- Produces: public summary blocks for live contests, published competition coverage, and department standings without exposing private entry data.

- [ ] **Step 1: Pass public collection counts into the hero**

Extend `BroadcastHero` with `competitionCount` while retaining its existing event, live-contest, leaderboard, freshness, and authentication inputs.

- [ ] **Step 2: Render three quick-information cards**

Show Live now, Sports programme, and Department table counts immediately below the landing headline, with truthful zero states.

- [ ] **Step 3: Retain detailed public blocks**

Keep the live score carousel, championship standings, published schedules, brackets, and cover imagery below the summary row.

- [ ] **Step 4: Run public privacy coverage**

Run: `php artisan test tests/Feature/Public/PublicLandingTest.php`
Expected: all landing payload, privacy, no-event, leaderboard, and bracket assertions pass.

### Task 4: Visual and regression verification

**Files:**
- Verify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Verify: `resources/js/Pages/Dashboard.jsx`
- Verify: `resources/js/Pages/Welcome.jsx`

**Interfaces:**
- Consumes: completed responsive UI.
- Produces: a build-verified handoff with no backend-contract changes.

- [ ] **Step 1: Run formatting-sensitive production compilation**

Run: `npm.cmd run build`
Expected: exit code 0 and generated Vite assets.

- [ ] **Step 2: Run combined focused tests**

Run: `php artisan test tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Public/PublicLandingTest.php`
Expected: all tests pass.

- [ ] **Step 3: Review responsive and accessible states**

Confirm desktop sidebar, mobile drawer, active routes, keyboard focus, semantic landmarks, truthful empty states, and reduced-motion-safe transitions.

- [ ] **Step 4: Review working-tree scope**

Run: `git diff -- resources/js/Components/ApplicationLogo.jsx resources/js/Layouts/AuthenticatedLayout.jsx resources/js/Pages/Dashboard.jsx resources/js/Pages/Welcome.jsx docs/superpowers/plans/2026-08-10-syntix-admin-dashboard-redesign.md`
Expected: only the requested frontend redesign and its plan appear.
