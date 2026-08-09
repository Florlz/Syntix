# SYNTIX Product And Domain Contract

**Document role:** Canonical product scope, domain boundaries, and cross-cutting invariants
**Design status:** Baseline for implementation
**Implementation status:** See [`implementation-status.md`](implementation-status.md)
**Last updated:** August 9, 2026

This document is the compact product contract for SYNTIX, the CSPC SIKLAB intramurals operations platform. Focused specifications own detailed workflows and acceptance criteria; ADRs own architectural decisions; the centralized decision register owns unresolved institutional rules.

## 1. Purpose

SYNTIX replaces paper-based intramurals tallying with auditable digital scoring, timely public result publication, and transparent delegation championship standings while preserving the authority of Judges, referees, tournament managers, Tabulators, and the adjudication committee.

The platform supports multiple SIKLAB editions. Event administrators configure dates, participating delegations, competitions, Divisions, formats, criteria, rosters, schedules, and point rules for each edition.

## 2. Source Hierarchy

When sources disagree, use this order:

1. Product-owner decisions recorded in the project conversation.
2. [`Approved-2025-Intramurals-Proposal.pdf`](Approved-2025-Intramurals-Proposal.pdf) for official 2025 competition rules, formats, criteria, rosters, and point schedules.
3. [`MANUSCRIPT FINAL 123 PDF COPY.pdf`](MANUSCRIPT%20FINAL%20123%20PDF%20COPY.pdf) for product objectives, reports, evaluation requirements, and offline intent.
4. [`SYNTIX.docx`](SYNTIX.docx) for the intended Admin, Judge, Tabulator, and Public workflows.
5. This repository for the implemented stack, schemas, policies, and established conventions.

The repository is authoritative over manuscript references to Laravel 12, Bootstrap, MySQL, and Firebase. The implementation uses Laravel 13, React with Inertia, Tailwind CSS, PostgreSQL, and Laravel-native services. Reverb/Echo remains the approved future realtime transport, not a current dependency.

## 3. Authoritative Architecture

| Boundary | Authority |
|---|---|
| Identity and authorization | Laravel sessions, policies, event roles, and scoring assignments |
| Configuration and lifecycle | Laravel actions, Eloquent models, migrations, and PostgreSQL constraints |
| Scoring and approvals | Server-authoritative actions with revisions, idempotency, and transactions |
| Official standings | Approved Division Placements and append-only signed ledger entries |
| Browser state | UI state and an ordered command outbox only; never authoritative standings |
| Public delivery | Sanitized HTTP DTOs first; realtime delivery is an optional after-commit transport |
| Public cache | Public shell and safe read-only snapshots only; authenticated Inertia responses remain private |

The first release keeps normal Inertia navigation and Laravel session authentication. JSON commands, IndexedDB synchronization, queues, and realtime channels are added only at explicit boundaries.

## 4. Confirmed Product Decisions

- Admin, Judge, and Tabulator are separate authenticated Event Roles.
- `event_creator` is a platform capability and does not grant an Event Role automatically.
- Event Admin is event-scoped and does not imply platform-level event creation.
- Public access is anonymous, read-only, and not a stored Event Role.
- Judges and Tabulators require both the matching Event Role and an active exact Scoring Assignment.
- Assignment scopes are only `competition_division`, `contest`, and `entry_scorecard`.
- Live scores may be public, but every pre-approval value is explicitly `Unofficial`.
- A Result Submission is not an Official Contest Outcome; an approved contest outcome is not a final Division Placement.
- Only an approved final Division Placement can create official championship-point ledger effects.
- Championship totals are derived from the signed append-only ledger, never manually edited.
- Rule versions freeze when scoring begins; corrections append history rather than overwrite it.
- SYNTIX records organizer-approved draw order but never conducts the draw.
- BYEs are auditable automatic resolutions, not played contests, wins, losses, forfeits, scores, or points.
- Offline commands are subordinate to server authority and require UUIDs, revisions, durable receipts, dependency ordering, and human-reviewed conflicts.
- Pageants remain outside the initial release.

## 5. Scope

### Included

- Multiple SIKLAB editions, event lifecycle, delegations, event colors, venues, and schedules.
- Competitions, score-bearing Divisions, Disciplines, configurable formats, criteria, roster limits, and point rules.
- Participants, Entries, teams, pairs, relays, reserves, coaches, and eligibility status.
- Judge scorecards, objective live scoring, result submissions, approval, correction, void, and audit history.
- Single elimination, round robin, configured series, aggregate placement, and judged competition families.
- Public schedules, live unofficial scores, published brackets, approved results, Division standings, and championship standings.
- PWA installability and safe public snapshots with HTTP fallback.

### Excluded From The Initial Release

- Student self-service registration.
- Medical certificates, parent consent, or eligibility-document uploads.
- Ticketing, budgeting, procurement, honoraria, and liquidation management.
- Video streaming, automated judging, or replacement of official human decisions.
- Protest-fee collection and automatic draw generation.
- Pageant scoring.

Manual roster encoding is in scope even though public registration is not.

## 6. Domain Structure

The shared vocabulary is defined in [`domain-glossary.md`](domain-glossary.md). The core hierarchy is:

```text
Event
  -> Organizational Unit / event-scoped Delegation
  -> Competition
      -> Division
          -> Discipline
          -> Contest
              -> Entry / Participant
```

- **Event:** one SIKLAB edition.
- **Organizational Unit:** reusable CSPC college, campus, or participating institution.
- **Delegation:** an Organizational Unit's representation in one Event.
- **Competition:** an activity family such as Basketball, Athletics, or Pop Solo.
- **Division:** the score-bearing category, including an explicit Open or default Division when needed.
- **Discipline:** a Division sub-event such as a race or weight class.
- **Contest:** one match, heat, race, round, or judged performance.
- **Entry:** a team, individual, pair, relay, or other delegation representative.

## 7. Officiality And Lifecycles

Contest performance and official records are separate state machines:

```text
Contest: scheduled -> live -> completed
             |          |
             v          v
          cancelled  suspended

Submission: draft -> completed -> submitted -> approved
                                      |
                                      v
                                   rejected -> draft

Official outcome: approved current -> superseded by correction
                         |
                         v
                       voided

Division placement: candidate -> submitted -> approved current
                                    |
                                    v
                                  rejected -> candidate
```

- Live and completed performance can remain unofficial.
- Result rejection requires a reason and a new editable revision.
- Contest-outcome approval may advance a bracket or update internal Division standings, but creates no championship points.
- Final Division-placement approval atomically creates the eligible official placement and signed ledger effects.
- Corrections and voids preserve prior records and use reversals or replacements where the final placement changes.

## 8. Scoring Layers

SYNTIX keeps three scoring layers separate:

1. **Contest performance:** points, periods, sets, rounds, measurements, Judge values, or status during a Contest. Live public values are unofficial.
2. **Division Sub-Points:** internal aggregate-Division values from Discipline placements. They rank a Division and never enter the championship ledger directly.
3. **Championship Points:** delegation points created only from an approved final Division Placement under its frozen Point Rule Version.

Initial reference point templates are versioned configuration, not constants in business logic:

| Template | Champion | 1st Runner-Up | 2nd Runner-Up | Participation |
|---|---:|---:|---:|---:|
| Major | 25 | 20 | 15 | 5 |
| Standard | 20 | 15 | 10 | 5 |
| Individual | 5 | 4 | 3 | 1 |
| Intermediate | 8 | 6 | 4 | 2 |

The exact 2025 values and exceptions remain subject to the source materials and [`open-decisions.md`](open-decisions.md).

## 9. Authorization Boundary

| Role or capability | Authority |
|---|---|
| `event_creator` | Creates an Event shell and separately grants its first Event Admin |
| Admin | Configures an Event, provisions accounts, creates assignments, reviews results, approves placements, and manages reports |
| Judge | Drafts and submits only assigned criterion scorecards |
| Tabulator | Records live and completed objective results only for assigned Contests |
| Public | Reads explicitly published sanitized data without an account |

Navigation is never authorization. Policies and server-side queries must enforce the active Event Role and matching assignment on every protected read and mutation.

## 10. Public Data And Privacy

- Public DTOs are purpose-built allow-lists, not serialized Eloquent models.
- Public payloads exclude student numbers, contacts, private rosters, eligibility notes, Judge drafts, assignment identities, audit metadata, queued commands, and internal correction reasons.
- Delegation labels are the default public identity; participant names require a separate approved publication setting.
- Public live performance is labeled `Unofficial` and includes an authoritative update timestamp.
- Official standings are derived only from committed approved records.
- Public HTTP reads remain functional before and after realtime installation.
- Service-worker caching is limited to public shell/assets and safe GET snapshots with freshness/offline labels.

## 11. Offline And Realtime Boundaries

Online server-authoritative mutation comes first. Each scoring command carries a client UUID, actor/Event scope, target, payload, expected revision, and optional dependency. The server persists a terminal Command Receipt and rejects stale or conflicting commands explicitly. A repeated UUID is safe only when the actor scope and canonical envelope match.

The later offline phase may queue command intents in IndexedDB, but it may not store authoritative standings or auto-merge conflicts. A close or submission completes only after the server acknowledges the exact revision.

Laravel Reverb and Echo are the target for post-commit sanitized updates, but they are not currently installed. HTTP snapshots and refresh are complete behavior, not a temporary placeholder.

## 12. Operational Contract

Focused technical contracts live in [`docs/specs/`](specs/):

- Identity, roles, and assignments.
- Competition scoring and rule versions.
- Tournament formats and bracket routing.
- Basketball tracer.
- Judged scoring.
- Athletics aggregation.
- Offline synchronization.
- Admin frontend.
- Public scoreboard and landing surface.
- Reports and archive.

Each contract must state its authorization, data boundary, lifecycle, failure behavior, acceptance criteria, and relevant decision-register entries without copying unrelated product requirements.

## 13. Cross-Cutting Invariants

- Laravel and PostgreSQL are authoritative.
- Every scorer mutation requires the correct Event Role, exact active assignment, expected revision, and idempotency key.
- Rule versions become immutable when scoring begins.
- Criteria weights must total exactly 100 percent before activation.
- Required scorecards and measurements must exist before approval.
- Unresolved ties block approval unless a configured tie-breaker or authorized ruling is recorded.
- Match approval never creates championship points.
- Only final Division-placement approval creates eligible ledger effects exactly once.
- Ledger totals equal the sum of all committed signed entries, including awards, reversals, and replacements.
- Official data is never silently overwritten or destructively deleted.
- Sensitive participant data never reaches public broadcasts, caches, or DTOs.

## 14. Change Policy

When a rule, privacy boundary, role, lifecycle, or data invariant changes:

1. Record or update the relevant ADR or decision-register entry.
2. Update the focused specification and its tests.
3. Update [`implementation-status.md`](implementation-status.md) if delivery state changes.
4. Keep source artifacts and historical official records traceable.

Do not resolve an institutional ambiguity in code by guessing. Block the related activation or approval path until the decision is recorded.
