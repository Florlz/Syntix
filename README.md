# SYNTIX

SYNTIX is the CSPC SIKLAB intramurals operations platform. It provides event configuration, scoped staff workflows, server-authoritative scoring, approvals, brackets, delegation standings, public event broadcasts, and an auditable score ledger.

The application is designed for multiple SIKLAB editions. The approved 2025 materials provide the initial reference configuration; they do not hard-code future event dates, rules, delegations, or point schedules.

## Current Status

The repository contains a working proposal-backed implementation across identity, event setup, sports and divisions, registrations and rosters, schedules and publishing, tournament generation, Judge and Tabulator operations, result approvals, public scoreboards, and the championship-point ledger.

Event staff use invitation-only accounts. Judges receive exact scorecards through locked judging panels; Tabulators receive division or contest assignments. Their role dashboards are schedule-first and expose only server-authorized work. Account setup supports private link sharing and locally generated printable QR handoff cards.

Known incomplete or intentionally blocked areas include Laravel Reverb/Echo delivery, PDF report/archive generation, broader offline coverage outside the objective-scoring command queue, and proposal rules whose printed values conflict or omit required detail. Conflicting source rules remain blocked rather than guessed.

## Stack

- Laravel 13 and PHP 8.4+
- PostgreSQL as the application authority
- React 18 with Inertia 2
- Tailwind CSS 4 and Vite 8
- Laravel session authentication
- PHPUnit 12 and Laravel Pint
- Installable PWA shell with public-only runtime caching

Laravel and PostgreSQL remain authoritative for authorization, scoring, approvals, brackets, official standings, and ledger totals. Browser storage and realtime transports are delivery mechanisms, not alternate sources of truth.

## Run With Docker

Docker Compose runs Laravel's PHP server, Vite, PostgreSQL, pgAdmin, and the
queue worker. Laravel Sail and Laravel Herd are not required.

After installing and starting Docker Desktop, open PowerShell in the repository
and run:

```powershell
.\scripts\setup-docker.ps1
```

Open <http://localhost:8000>. Vite runs in its own container and automatically
hot-reloads frontend changes. Start the stack later with:

```powershell
docker compose up -d --wait
```

The command returns only after PostgreSQL, Vite, and Laravel are ready. If you
change `.env`, restart the Laravel workers with `docker compose restart app`.
Frontend pages, layouts, and components are warmed automatically by Vite. Use
`PrefetchLink` for new internal GET navigation so it inherits the shared
hover-prefetch cache; mutation links remain opt-out automatically.

PostgreSQL can be inspected at <http://localhost:5050> through pgAdmin. Sign in
with `admin@example.com` / `password`, open **Syntix PostgreSQL**, and enter the
database password `password` when prompted. Change these local credentials in
`.env` if the services will be exposed beyond your machine.

Common verification and maintenance commands run inside the containers:

```powershell
docker compose exec -T app php artisan test --compact
docker compose exec -T app php vendor/bin/pint --test
docker compose exec -T app php artisan migrate
docker compose exec -T vite npm run test:ui -- --run
docker compose exec -T vite npm run build
docker compose -f compose.yaml -f compose.contract.yaml --profile contract run --rm contract
```

The default local seed creates `admin@syntix.test` with password `password`,
the SIKLAB 2026 event, its sports, divisions, departments, and governing rule
configuration. It intentionally creates no players, rosters, schedules, venues,
contests, scoring staff, assignments, or results. Disposable showcase data is
opt-in:

```powershell
docker compose exec -T app php artisan db:seed --class=DevelopmentShowcaseSeeder
```

View application, Vite, or queue-worker logs with `docker compose logs -f app`,
`docker compose logs -f vite`, or `docker compose logs -f queue`. Stop the full
development stack with:

```powershell
docker compose down
```

## Documentation

Current behavior is defined by the application source, migrations, and tests.
Use these documents for orientation and delivery history:

- [`agents/context.md`](agents/context.md): durable product, domain, architecture, privacy, and workflow conventions
- [`docs/handoffs/`](docs/handoffs/): delivered behavior, verification evidence, and continuation notes
- [`docs/superpowers/`](docs/superpowers/): approved design and implementation records retained in the repository

The PDF and DOCX files under `docs/` are preserved institutional source
artifacts. When those sources conflict or omit required rules, the affected
workflow remains blocked until an authorized interpretation is recorded.

## Judge and Tabulator Event-Day Flow

1. The Global Admin creates the staff identity and event role under **Judges & Tabulators → People**.
2. Syntix displays a one-time setup link. The Admin shares it privately or prints the named QR handoff card.
3. The staff member creates a password. If another account is already signed in, Syntix requires an explicit account switch instead of silently redirecting.
4. Judge-only accounts open **My Judging**; Tabulator-only accounts open **My Tabulation**. Dual-role accounts choose a workspace.
5. Assignments are managed separately: Judges through judging panels, Tabulators through division or contest assignments.
6. Schedule timelines show time, venue, state, and the next allowed action. Corrections, blockers, live work, and contests ready to finalize are also surfaced under **Needs attention**.

## Scope Boundaries

The initial product includes team sports, individual sports, combat sports, Athletics, judged competitions, literary and musical activities, dance, visual arts, academic contests, and Esports. Pageants, student self-registration, medical-document uploads, ticketing, budgeting, video streaming, and automated judging are outside the initial scope.

Public access is anonymous and read-only. Live values are explicitly unofficial. Only an approved final Division Placement can create official signed championship-point ledger entries.

## License

This project is intended for CSPC SIKLAB operations. The repository currently retains the Laravel application's MIT license metadata; institutional distribution and branding terms remain subject to CSPC approval.
