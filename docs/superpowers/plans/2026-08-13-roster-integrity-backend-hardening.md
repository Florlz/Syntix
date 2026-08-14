# Admin Roster Reliability and Backend Hardening

## Goal

Make sport roster work predictable for administrators: one canonical participant shape, reliable selection and filtering, one atomic player-management save, explicit player/staff/history sections, and backend rules that remain safe for locked, published, and archived event data.

This plan extends the current roster workflow. It does not replace or overwrite the completed roster workflow plan, the current product direction, existing routes, public history, or unrelated working-tree changes.

## Guardrails

- Preserve all existing records, audit history, route names, public snapshots, and mutation endpoints kept for compatibility.
- Treat archived events as read-only. The existing meaning of the separate `closed` event state remains unchanged.
- Keep participant documents and medical permits deferred.
- Keep judged-scorecard and athletics approval UI deferred.
- Use the containers for Laravel, frontend, and PostgreSQL verification.
- Do not silently coerce invalid historical state while adding database constraints. Preflight and abort with an actionable error.

## Work plan

### 1. Canonical roster contract

- Make the participant DTO keyed by `id` and `display_name` everywhere in the roster surface.
- Include nullable `membership`, nullable `eligibility`, and server-derived participant capabilities.
- Return server-calculated counts for active players, team staff, roster history, and pending player eligibility.
- Apply eligibility and pending counts only to `student_athlete` and `reserve`; retain coach eligibility history but do not present it as a requirement.
- Return entry-level allowed actions for editable, locked, published, and archived rosters so the client does not recreate backend rules.
- Keep compatibility payloads/routes where needed, but remove conflicting client-side assumptions.

### 2. Atomic roster-player management

Add `PUT /admin/events/{event}/entries/{entry}/players/{participant}` as `admin.roster-players.update` with nested `profile`, `membership`, and `eligibility` fields.

- Validate event, department, division, participant, role, eligibility, and archive state together.
- Save changed shared profile fields, roster membership, and eligibility in one outer transaction; skip unchanged sections.
- Do not expose shared participant deactivation from the sport panel.
- Preserve an existing eligibility decision during harmless role/note edits.
- Create `pending` only for a new player eligibility record; preserve an old `eligible` or `pending` status when restoring an inactive member.
- Require an explicit `eligible` or `pending` choice when restoring an adverse record; preserve adverse history and continue to deactivate memberships for adverse decisions.
- Retain the existing single-section endpoints for compatibility.

### 3. Roster interface

- Use `participant.id` for keys, checkbox values, selection, lookup, and requests.
- Reset selection on department/entry changes and prune IDs that are filtered out or no longer available.
- Present active players, team staff, and collapsed inactive roster history as separate sections.
- Keep eligibility controls on players only; coaches have no eligibility controls.
- Rename filters to `Departments needing work` and `Eligibility issues only`, placing each beside the content it filters.
- Show a useful filtered-empty state instead of a blank table.
- Replace immediate bulk submission with a confirmation panel naming every selected person, target status, and reason; adverse changes require a reason.
- Use one Manage panel with profile, membership, and eligibility sections and one atomic `Save changes` action.
- Respect server capabilities on locked, published, and archived rosters while retaining permitted adverse corrections.
- Prevent imported/stale IDs from submitting hidden or already-rostered people; clear selection and close the add panel after success.
- Retain the restrained Syntix palette, flat work surfaces, department color strips, and plain operational copy.

### 4. Models, enums, constraints, and archive hardening

Introduce bounded enums and model casts for tournament format, discipline placement/entry states, bracket-node states, advancement outcomes, and bracket-slot sources. Add an additive migration with literal, frozen constraint lists that preflights existing data before adding PostgreSQL checks.

- Remove only demonstrably unused `CompetitionDivision`, `EntryMember`, and `JudgeScorecard` aliases and alias policies after reference checks; keep canonical `Division`, `RosterMember`, and `EntryScorecard` names and database tables.
- Remove duplicate compatibility relationships and rename the misleading `CompetitionRuleVersion::event()` helper to a non-relationship name.
- Correct and test `ScoringAssignment::matches()` for division, contest, and entry-scorecard targets.
- Centralize the mutable-event/archive guard and apply strict archive rejection to brackets, rules, scoring assignments, contest approvals/rejections, placements, sub-points, rosters, schedules, and publishing. Archived data remains readable and cannot mutate or change the ledger.
- Freeze state lists used by historical migrations so future enum additions cannot rewrite migration history.

### 5. Verification

Add focused Vitest/React Testing Library coverage for selection identity, unique keys and accessible names, section rendering, filter/reset behavior, bulk confirmation, restore behavior, atomic Manage context, locked/published/archived states, and filtered-empty states.

Add Laravel contract/feature coverage for the canonical DTO, eligibility preservation/restoration, coach exclusion, atomic rollback, containment, archive rejection, enum/model/constraint parity, scoring-assignment matching, and safe alias removal. Keep the SQLite suite and add an ephemeral PostgreSQL contract compose service.

Run:

```powershell
docker compose exec -T app php artisan test
docker compose exec -T vite npm run test:ui -- --run
docker compose exec -T vite npm run build
docker compose -f compose.yaml -f compose.contract.yaml --profile contract run --rm contract
```

Finish with desktop/mobile browser checks for selection, Manage, Restore, bulk confirmation, locked corrections, keyboard focus, and archived read-only behavior.

## Assumptions

- The current live relational audit found no cross-event or cross-division corrupt records.
- The existing Laravel suite is the regression baseline.
- No records, audit history, brackets, results, or published snapshots are deleted.

## Implementation log

- Implemented the canonical roster DTO, atomic player endpoint, player/staff/history interface, stale-ID protection, and adverse/locked/archive rules.
- Added bounded state enums, model casts, literal historical migration lists, PostgreSQL preflight/check migration, archive guard, alias cleanup, and scoring-assignment matching coverage.
- Added Vitest/React Testing Library setup and the ephemeral PostgreSQL contract compose service.
- Made the additive combat-discipline migration portable to SQLite by rebuilding the enum column through Laravel's schema grammar; historical migration values remain frozen.
- Container verification completed: Laravel 118 passed, 2 skipped (1,069 assertions); UI 2 passed; Vite production build passed; PostgreSQL contract 2 passed (7 assertions).
