# ADR 0001: Authoritative Application Stack

## Status

Accepted

## Date

2026-08-09

## Context

The manuscript and supporting workflow summary contain proposed or obsolete stack claims that conflict with the checked-in application. A single authoritative stack is required so implementation and research documentation do not diverge further.

## Decision

The repository stack is authoritative: Laravel 13, Inertia with React, Tailwind CSS, and PostgreSQL. Laravel remains the server and data authority. Realtime delivery and PWA capabilities must integrate with this stack rather than introduce a second authoritative backend.

Claims that SYNTIX uses Firebase, MySQL, Bootstrap, or Laravel 12 are rejected for implementation purposes and must not drive new code or schema decisions.

## Consequences

- New backend behavior uses Laravel conventions and PostgreSQL transactions, constraints, and concurrency controls.
- Inertia and React implement authenticated and public application screens; Tailwind implements styling.
- Offline browser state and realtime transports remain subordinate to Laravel and PostgreSQL.
- Manuscript wording must eventually be reconciled with the implemented stack outside this ADR change.

## Rejected Alternatives

- Adopt Firebase for synchronization or data authority: rejected because it creates a second source of truth and conflicts with the repository direction.
- Replace PostgreSQL with MySQL: rejected because the repository and planned locking tests target PostgreSQL.
- Replace Inertia React and Tailwind with server-rendered Bootstrap pages: rejected because it discards the established frontend foundation.
- Downgrade to Laravel 12: rejected because the application already targets Laravel 13.

## References

- [SYNTIX product and domain contract](../cspc-siklab-plan.md)
- `composer.json`
- `package.json`
- `config/database.php`
