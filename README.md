# SYNTIX

SYNTIX is the CSPC SIKLAB intramurals operations platform. It provides event configuration, scoped staff workflows, server-authoritative scoring, approvals, brackets, delegation standings, public event broadcasts, and an auditable score ledger.

The application is designed for multiple SIKLAB editions. The approved 2025 materials provide the initial reference configuration; they do not hard-code future event dates, rules, delegations, or point schedules.

## Current Status

The repository contains the first working implementation across identity, event foundations, scoring, approvals, bracket generation, Judge and Tabulator workflows, Admin operations, public scoreboard pages, and a public CSPC landing surface.

Known incomplete or intentionally blocked areas include Laravel Reverb/Echo delivery, the full offline synchronization phase, PDF report/archive generation, complete 2025 rule seeding, and double-elimination routing until each supported size has institutional sign-off.

See [`docs/implementation-status.md`](docs/implementation-status.md) for the current implementation map, verification evidence, and next slices.

## Stack

- Laravel 13 and PHP 8.3+
- PostgreSQL as the application authority
- React 18 with Inertia 2
- Tailwind CSS 3 and Vite 8
- Laravel session authentication
- PHPUnit 12 and Laravel Pint
- Installable PWA shell with public-only runtime caching

Laravel and PostgreSQL remain authoritative for authorization, scoring, approvals, brackets, official standings, and ledger totals. Browser storage and realtime transports are delivery mechanisms, not alternate sources of truth.

## Run With Docker

Host PHP is not required. The Compose file provides the app, Vite, and PostgreSQL services.

```bash
docker compose up -d
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec vite npm run build
```

The app service installs Composer dependencies and runs migrations on startup. Stop the stack with:

```bash
docker compose down
```

## Documentation

Start with [`docs/index.md`](docs/index.md). It defines the reading order and the source-of-truth boundaries.

- [`docs/cspc-siklab-plan.md`](docs/cspc-siklab-plan.md): canonical product and domain contract
- [`docs/implementation-status.md`](docs/implementation-status.md): implementation state and roadmap
- [`docs/open-decisions.md`](docs/open-decisions.md): centralized institutional decision register
- [`docs/domain-glossary.md`](docs/domain-glossary.md): shared vocabulary
- [`docs/adr/`](docs/adr/): accepted architectural decisions
- [`docs/specs/`](docs/specs/): focused technical contracts

## Scope Boundaries

The initial product includes team sports, individual sports, combat sports, Athletics, judged competitions, literary and musical activities, dance, visual arts, academic contests, and Esports. Pageants, student self-registration, medical-document uploads, ticketing, budgeting, video streaming, and automated judging are outside the initial scope.

Public access is anonymous and read-only. Live values are explicitly unofficial. Only an approved final Division Placement can create official signed championship-point ledger entries.

## License

This project is intended for CSPC SIKLAB operations. The repository currently retains the Laravel application's MIT license metadata; institutional distribution and branding terms remain subject to CSPC approval.
