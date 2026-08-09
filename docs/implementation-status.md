# Implementation Status

**Status:** Active worktree implementation; not a release
**Last verified:** August 9, 2026
**Canonical product contract:** [`cspc-siklab-plan.md`](cspc-siklab-plan.md)

This document replaces the dated implementation plans. It records the current delivery state rather than a historical task checklist.

## Delivered

### Identity And Event Foundation

- Account state, closed privileged provisioning, one-time bootstrap, and event-creator capability.
- Event shells, organizational units, event-scoped delegations, event roles, and exact scoring assignments.
- Policy checks, authorization denials, audit records, session invalidation, and last-event-creator protection.
- Public registration and self-service privileged-account creation are disabled.

### Configuration And Scoring Core

- Competition, Division, Discipline, Contest, Entry, roster, eligibility, venue, and schedule domain foundations.
- Versioned rule and point-template structures with activation validation.
- Live contest revisions, Judge scorecards, result submissions, official contest outcomes, Division Placements, and signed score-ledger entries.
- Separate contest-outcome approval from final Division-placement approval.
- Athletics discipline placements and configured Sub-Point aggregation.
- Idempotent scoring commands, expected revisions, command receipts, and server-authoritative mutations.

### Tournament And Public Workflows

- Deterministic single-elimination and round-robin generation with zero/one-entry safeguards, BYEs, and third-place handling where supported.
- Published bracket versions with sanitized anonymous public views.
- Double-elimination generation intentionally remains blocked until supported sizes have signed-off routing maps.
- Public scoreboard, public landing page, event directory, and delegation-only public labels.
- Anonymous public cache boundaries and authenticated private/no-store response handling.

### Operational Frontends

- CSPC-styled Admin dashboard and approval queue.
- Assignment-scoped Judge scorecard workbench.
- Assignment-scoped Tabulator contest workflow.
- Public scoreboard and bracket pages.
- PWA shell with public-only runtime caching and HTTP fallback.

## Partial Or Deferred

### Competition Configuration

The domain foundation and reference seed are present, but the complete Admin configuration experience and full 2025 competition/rule/criteria seed are not complete. The seven reference organizational units and point templates are reusable seed data, not a complete event configuration.

### Brackets

Single elimination and round robin are implemented as the current tracer subset. Double elimination is blocked until CSPC signs off every supported bracket size's winner routes, loser routes, BYE behavior, placement extraction, and reset-final transitions.

### Offline Synchronization

The command processor, durable receipts, and browser outbox prototype exist. The full account-partitioned outbox lifecycle, close handshake UX, queue retention, device-loss contingency, and human conflict workflow remain incomplete. IndexedDB never becomes authoritative.

### Realtime

HTTP is the current public delivery path. Laravel Reverb/Echo dependencies, channels, after-commit broadcast events, and operational monitoring are not installed.

### Reports And Archive

The current Admin report surface provides a CSV championship report. PDF generation, immutable report manifests, report jobs, archive artifacts, certification metadata, and full correction-history reports remain future work.

### Verification Environment

The default PHPUnit configuration uses in-memory SQLite. PostgreSQL-backed concurrency and locking verification still requires a dedicated integration-test configuration; Docker Compose provides PostgreSQL for local development.

## Next Delivery Slices

1. Finish the Admin configuration and event-readiness workflows around the existing domain actions.
2. Complete the Basketball single-elimination tracer and its correction/downstream-routing behavior.
3. Resolve the institutional decisions in [`open-decisions.md`](open-decisions.md) before activating affected competition families.
4. Complete offline close/conflict flows and add PostgreSQL concurrency coverage.
5. Install Reverb/Echo only after the HTTP public contracts are stable.
6. Expand reports, archive artifacts, accessibility/browser coverage, and operational runbooks.

## Verification Evidence

Latest verified commands:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
npm run build
git diff --check
```

Latest results:

- PHPUnit: 68 tests passed, 349 assertions.
- Pint: 220 files passed.
- Vite/PWA production build: passed.
- Git whitespace check: passed.

## Implementation Map

| Area | Primary locations |
|---|---|
| Domain and persistence | `app/Models/`, `app/Enums/`, `database/migrations/` |
| Actions and services | `app/Actions/`, `app/Services/` |
| Authorization | `app/Policies/`, `app/Http/Middleware/` |
| Admin | `app/Http/Controllers/Admin/`, `resources/js/Pages/Admin/` |
| Judge | `app/Http/Controllers/Judge/`, `resources/js/Pages/Judge/` |
| Tabulator | `app/Http/Controllers/Tabulator/`, `resources/js/Pages/Tabulator/` |
| Public | `app/Http/Controllers/PublicArea/`, `resources/js/Pages/Public/`, `resources/js/Pages/Welcome.jsx` |
| Offline prototype | `app/Services/ScoringCommandProcessor.php`, `resources/js/lib/commandOutbox.js` |
| Tests | `tests/Feature/`, `tests/Unit/` |

## Status Rules

- “Delivered” means code and focused tests exist; it does not mean every institutional rule is approved for production.
- “Partial” means the current code is useful but does not satisfy the complete product contract.
- “Blocked” means implementation must not guess an unresolved institutional rule.
- Every status change should update this file and the relevant focused specification in the same documentation change.
