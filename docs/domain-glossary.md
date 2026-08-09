# SYNTIX Domain Glossary

This glossary is the shared vocabulary for code, database names, interface copy, tests, and documentation. It summarizes terms established by the [SYNTIX product and domain contract](cspc-siklab-plan.md); the contract remains authoritative for workflows, rules, and scope.

## Core Structure

| Term | Definition |
|---|---|
| Event | One SIKLAB edition with its own lifecycle, configuration, delegations, roles, and records, such as SIKLAB 2026. |
| Organizational Unit | A reusable CSPC college, campus, or other institution unit that may participate across events. |
| Delegation | An organizational unit's event-scoped representation, including that edition's display name, abbreviation, color, and eligibility. |
| Competition | An activity family configured within an Event, such as Basketball, Athletics, or Pop Solo. It groups one or more Divisions but does not itself own a final placement or Point Rule Version. |
| Division | The score-bearing category within a Competition, such as Basketball Men, Basketball Women, or Basketball Open. Final placement and the applicable Point Rule Version attach to the Division. A Competition without named categories still has an explicit default or Open Division; a null or implicit Division is not permitted. |
| Discipline | A Division sub-event, such as Women's 100m or Taekwondo 53kg, that may produce its own outcome, placement, or Sub-Points. |
| Contest | One scheduled unit of play or evaluation, such as a match, heat, race, round, or judged performance. |
| Entry | The team, individual, pair, relay, or other delegation representative competing in a Division, Discipline, or Contest. |
| Participant | A person registered in an event who may belong to one or more entries subject to roster and eligibility rules. |

## Authorization

| Term | Definition |
|---|---|
| Platform Capability | An authorization outside any Event. The `event_creator` Platform Capability permits creation of an Event but grants no authority inside an existing Event. |
| Bootstrap Context | The one-time deployment authorization context that creates the first active user with `event_creator`. It is then disabled or becomes a no-op; it is not an Event Role and grants no Event authority. |
| Event Role | An event-scoped authenticated responsibility: Admin, Judge, or Tabulator. It does not by itself grant a Judge or Tabulator access to scoring data. |
| Scoring Assignment | An explicit, revocable authorization connecting a Judge or Tabulator to exactly one supported scope: `competition_division`, `contest`, or `entry_scorecard`. Scope containment and overlap follow ADR 0002; there is no generic `other` scope. |
| Judge | An authenticated event role that records criterion-based scorecards only within active assignments. |
| Tabulator | An authenticated event role that records objective live and completed contest data only within active assignments; one Tabulator may have multiple assignments. |
| Admin | An authenticated event role that configures the event, provisions privileged accounts, manages assignments, and reviews official records. An Admin cannot silently rewrite frozen rules or derived standings. |

Public access is anonymous and read-only. `Public` is not an Event Role and creates no role or assignment record.

## Scoring And Results

| Term | Definition |
|---|---|
| Live Score | Mutable contest performance state that may be published but is always explicitly unofficial. |
| Scorecard | One Judge's criterion-level evaluation of one entry under a frozen scoring rule version. |
| Result Submission | A locked, revision-specific snapshot submitted by a scorer for Admin review. It is not yet official. |
| Official Contest Outcome | The Admin-approved outcome of one Contest, including winner, measurement, status, or ordered finish as applicable. It may advance a bracket or affect internal Division Standings. |
| Division Placement | The approved final ordering of eligible entries or delegations in one Division, derived from official contest outcomes, scorecards, measurements, or Sub-Points. |
| Point Rule Version | An immutable version of a Division's placement-to-Championship-Points mapping and its eligibility conditions. |
| Sub-Points | Internal aggregate-Division values awarded from Discipline placements. They rank the Division and never enter the championship ledger directly. |
| Championship Points | Event-level delegation points awarded from an approved final Division Placement under that Division's Point Rule Version. |
| Score Ledger Entry | An append-only, committed signed Championship-Points amount classified as an award, reversal, or replacement and linked to its source Division Placement, result revision, and Point Rule Version. |
| Standings | A derived ordered view. Contest or Division Standings use approved outcomes and placements. Each overall delegation total is the sum of the signed amounts of all committed Score Ledger Entries for that Event and Delegation, including awards, reversals, and replacements. Standings are never manually edited. |

## Tournaments And Brackets

| Term | Definition |
|---|---|
| Tournament | The format instance connecting one Division and its Competition family to eligible entries, rules, schedule, and bracket or round-robin structure. |
| Draw Order | The Admin-recorded order produced by an authorized manual draw or seeding process and used as deterministic bracket input. SYNTIX does not choose it. |
| Seed | An organizer-approved ranking or position used to determine an entry's Draw Order or initial slot; it is input, not a system-generated prediction. |
| BYE | An auditable automatic advancement caused by an empty bracket slot. It is not a played Contest, win, loss, Walkover, or Forfeit. |
| Walkover | An officially confirmed advancement because an expected opponent withdrew or could not contest before play. It requires an approved outcome or ruling. |
| Forfeit | An officially confirmed contest loss imposed for non-appearance or a rule violation. It is a played-or-scheduled contest outcome, not a BYE. |
| Bracket Version | An immutable published bracket structure, or a later audited replacement that preserves the earlier version. |
| Bracket Node | A directed-structure element representing a contest, BYE resolution, final, reset final, or third-place playoff. |
| Bracket Slot | One input position in a Bracket Node, populated by a direct Entry or by the winner or loser routed from another node. |
| Advancement Rule | The recorded rule that routes a node's winner, loser, or automatic BYE resolution to a later Bracket Slot. |
| Uncontested Division | A Division with exactly one eligible entry. It requires an authorized Admin-recorded ruling and does not automatically produce a champion or Championship Points. |

## Command Processing

| Term | Definition |
|---|---|
| Command Receipt | The server's persistent record of a mutation command, keyed by `command_uuid` and containing its disposition, authoritative response, and resulting revision when applied. |
| Revision Conflict | Rejection of a command because its expected base revision differs from current server state. It requires explicit review and is never merged automatically. |

## Ambiguity Warnings

- **event:** Use only for a complete SIKLAB edition. Use Competition for an activity family, Division or Discipline for a score-bearing category or sub-event, and Contest for a scheduled occurrence.
- **activity:** Avoid as a persisted domain noun because source documents use it at several levels. Replace it with Event, Competition, Division, Discipline, or Contest.
- **result:** Do not use alone in implementation names or status copy. State whether the value is a Live Score, Result Submission, Official Contest Outcome, or Division Placement.
- **points:** Always qualify the scoring layer. Distinguish contest score values, Judge criterion scores, Sub-Points, and Championship Points.

## References

- [SYNTIX product and domain contract](cspc-siklab-plan.md)
- [ADR 0002: Event Roles And Scoring Assignments](adr/0002-event-roles-and-assignments.md)
- [ADR 0003: Results, Placements, And The Score Ledger](adr/0003-results-placements-and-ledger.md)
- [ADR 0004: Offline Scoring Commands](adr/0004-offline-scoring-commands.md)
- [ADR 0005: Tournament Bracketing](adr/0005-tournament-bracketing.md)
