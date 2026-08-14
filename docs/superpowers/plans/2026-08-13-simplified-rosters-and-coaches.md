# Simplified Rosters and Coaches Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace per-player eligibility decisions with one roster approval at lock and add event-wide, scope-aware coach management.

**Architecture:** Active roster membership is the competition-ready state. Coach profiles reuse participants but receive dedicated assignments, while immutable roster approvals and participation exceptions preserve operational history. Existing eligibility data and routes remain compatible during the transition.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent, PostgreSQL/SQLite, Inertia React 18, Tailwind CSS, Vitest/RTL.

## Global Constraints

- Preserve all participant, eligibility, roster, audit, bracket, result, and publication records.
- Use additive migrations; archived events remain read-only.
- Preserve current route names as compatibility endpoints.
- Keep judges and tabulators under Event Staff; Coaches are non-login event people.
- Do not change the active model between Plan and implementation modes.

---

### Task 1: Add roster approval, coach assignment, and participation-exception persistence

**Files:** additive migration; focused enums/models; existing Event, Entry, and Participant relationships; PostgreSQL contract tests.

**Interfaces:** Produce `CoachAssignment`, `RosterApproval`, and `ParticipationException` records with event containment, bounded states, immutable historical rows, and active assignment uniqueness.

- [ ] Write migration and model contract tests for both SQLite and PostgreSQL.
- [ ] Add bounded enums, models, relationships, indexes, checks, and legacy backfill without deleting records.
- [ ] Run focused backend and migration tests.

### Task 2: Replace eligibility readiness with roster approval and exception actions

**Files:** roster membership/status actions, readiness/read models, registration controller/routes, tournament and discipline consumers.

**Interfaces:** Lock accepts `roster_review_confirmed`; active membership is cleared; adverse compatibility calls create participation exceptions; latest approval supplies locked competitor snapshots.

- [ ] Write failing feature tests for implicit clearance, confirmed lock, approval revisions, and exceptions.
- [ ] Implement transactional approval and exception services plus compatibility delegation.
- [ ] Remove eligibility from readiness and downstream tournament/discipline gates.
- [ ] Run focused feature tests.

### Task 3: Add coach directory and scope-aware assignments

**Files:** coach read/action services, registration controller/routes, programme rule metadata, directory UI.

**Interfaces:** Coaches use shared Participant profiles with Student/Faculty type, optional title, and division or programme-family assignment; maximums are enforced and missing coaches only warn.

- [ ] Write feature tests for coach-only profiles, dual player/coach identities, scope resolution, limits, archive rejection, and history.
- [ ] Implement coach assignment endpoints and event-directory payload.
- [ ] Add Players/Coaches URL-backed directory sections and coach forms.
- [ ] Run focused backend and UI tests.

### Task 4: Simplify the team-sheet UI and lock review

**Files:** roster page, Add Players panel, roster row component, dashboard/navigation summaries.

**Interfaces:** Team sheet renders separate Players and Coaches sections; eligibility controls/copy disappear; lock dialog submits the single roster-review confirmation and displays complete errors.

- [ ] Add UI tests for direct player addition, coach display, lock confirmation, empty/error states, and context preservation.
- [ ] Implement the simplified roster and dashboard copy.
- [ ] Run UI tests and production build.

### Task 5: Regression and contract verification

**Files:** affected feature/UI tests and handoff documentation.

- [ ] Run the full Laravel suite.
- [ ] Run the complete UI suite and Vite build.
- [ ] Run fresh PostgreSQL migrations and contract tests.
- [ ] Update the handoff with migration behavior, compatibility rules, and verification results.
