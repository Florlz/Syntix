# SYNTIX

SYNTIX is the CSPC SIKLAB intramurals operations platform. It provides event configuration, scoped staff workflows, server-authoritative scoring, approvals, brackets, delegation standings, public event broadcasts, and an auditable score ledger.

The application is designed for multiple SIKLAB editions. The approved 2025 materials provide the initial reference configuration; they do not hard-code future event dates, rules, delegations, or point schedules.

## Current Status

The repository contains a working proposal-backed implementation across identity, event foundations, scoring, approvals, randomized bracket generation, Judge and Tabulator workflows, Global Admin operations, public scoreboard pages, and a public CSPC landing surface.

Known incomplete or intentionally blocked areas include Laravel Reverb/Echo delivery, the full offline synchronization phase, PDF report/archive generation, and proposal rules whose printed values conflict or omit required detail.
The Global Admin participant/roster Registration Desk is the next approved
administrative slice and is not yet part of the verified implementation
baseline.

See the [product requirements](docs/prd/2026-08-09-syntix-product-prd.md) and [single system implementation plan](docs/plans/2026-08-09-syntix-system.md).

## Stack

- Laravel 13 and PHP 8.3+
- PostgreSQL as the application authority
- React 18 with Inertia 2
- Tailwind CSS 3 and Vite 8
- Laravel session authentication
- PHPUnit 12 and Laravel Pint
- Installable PWA shell with public-only runtime caching

Laravel and PostgreSQL remain authoritative for authorization, scoring, approvals, brackets, official standings, and ledger totals. Browser storage and realtime transports are delivery mechanisms, not alternate sources of truth.

## Run With Laravel Herd

Laravel Herd provides PHP and the local `syntix.test` web server. Docker Compose
is used only for PostgreSQL; PHP and Node do not run in containers.

After installing Herd and Docker Desktop, open PowerShell in the repository and
run:

```powershell
.\scripts\setup-herd.ps1
```

Link this repository as `syntix` in Herd, then open <http://syntix.test>. During
frontend development, keep Vite running in another terminal:

```powershell
npm run dev
```

PostgreSQL can be inspected at <http://localhost:5050> through pgAdmin. Sign in
with `admin@example.com` / `password`, open **Syntix PostgreSQL**, and enter the
database password `password` when prompted. Change these local credentials in
`.env` if the services will be exposed beyond your machine.

Common project commands use Herd's selected PHP version:

```powershell
herd php artisan test
herd php vendor/bin/pint --test
herd php artisan migrate
```

Start or stop the PostgreSQL service independently with:

```powershell
docker compose up -d postgres pgadmin
docker compose down
```

## Documentation

There are exactly two Markdown documentation authorities:

- [`docs/prd/2026-08-09-syntix-product-prd.md`](docs/prd/2026-08-09-syntix-product-prd.md): product requirements, canonical terminology, architecture decisions, technical contracts, and open institutional decisions
- [`docs/plans/2026-08-09-syntix-system.md`](docs/plans/2026-08-09-syntix-system.md): system implementation sequence and delivery evidence

The PDF and DOCX files under `docs/` are preserved institutional source
artifacts. They do not override the PRD when sources conflict.

## Scope Boundaries

The initial product includes team sports, individual sports, combat sports, Athletics, judged competitions, literary and musical activities, dance, visual arts, academic contests, and Esports. Pageants, student self-registration, medical-document uploads, ticketing, budgeting, video streaming, and automated judging are outside the initial scope.

Public access is anonymous and read-only. Live values are explicitly unofficial. Only an approved final Division Placement can create official signed championship-point ledger entries.

## License

This project is intended for CSPC SIKLAB operations. The repository currently retains the Laravel application's MIT license metadata; institutional distribution and branding terms remain subject to CSPC approval.
