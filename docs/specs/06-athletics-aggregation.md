# Athletics Aggregation

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines the Athletics aggregate tracer under the [product and domain contract](../cspc-siklab-plan.md). The product contract controls lifecycle, authorization, approval, ledger, correction, privacy, and architecture requirements.

- Confirmed product decisions: Athletics is the Competition family and a Division is the score-bearing unit; objective performance creates discipline placement and sub-points; sub-points do not enter the championship ledger; only approved final Division placement creates Major championship points.
- Proposal-derived 2025 rules: `docs/Approved-2025-Intramurals-Proposal.pdf`, especially pages 10-16, supplies Men and Women disciplines, track and field measurements, qualification and finals intent, individual sub-points `5/4/3/1`, relay sub-points `10/8/6/2`, no points for unplayed events, unfinished-final fallback language, and Major points `25/20/15/5`.
- Supporting intent: `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` identifies ascending time and descending distance ranking. Repository and master-plan stack decisions override its implementation references.
- Source contradiction and working interpretation: the proposal classifies Athletics as single elimination but also defines heats, measurements, qualification, discipline sub-points, and Men/Women score separation. This specification uses aggregate placement only as a working implementation interpretation, not a confirmed institutional format. Athletics rule activation is blocked until the format and Men/Women Division aggregation and championship-point treatment are confirmed. Pageants remain outside initial scope.

## Problem

Under the working aggregate interpretation, Athletics requires accurate measurements and rankings across many track, field, and relay disciplines before those discipline placements can be converted to sub-points and then to one or more final Division placements. Manual handling risks unit errors, inconsistent precision, accidental points for unplayed events, and direct addition of sub-points to the championship tally.

## Goals

- Record proposal-aligned Men and Women track, field, and relay results with explicit units and fixed precision.
- Support qualification rounds and finals without confusing preliminary performance with final placement.
- Rank time disciplines ascending and distance disciplines descending.
- Award versioned individual and relay sub-points and derive final Division placement under the confirmed aggregation rule.
- Convert only approved final Division placement to the Major `25/20/15/5` point schedule.
- Handle ties, disqualifications, cancelled disciplines, unfinished finals, corrections, and audit history safely.

## Non-Goals

- Activating either a knockout or aggregate Athletics format before the proposal contradiction and Men/Women treatment are resolved.
- Timing-device, photo-finish, or measuring-equipment integration in the first tracer.
- Athlete eligibility rules outside the proposal-derived Athletics roster and participation constraints in this specification.
- General bracket behavior, judged scoring, or offline synchronization.
- Medal tally reporting or pageant functionality.

## Users and Authorization

- A Tabulator requires an active Tabulator event role and an active assignment covering the Athletics Competition, Division, discipline, or contest to read private operational data or record results.
- An Admin configures frozen Divisions, disciplines, stages, units, precision, qualification, roster, participation, tie, status, sub-point, aggregation, and Major point rules before scoring.
- An Admin reviews and separately approves submitted discipline contest outcomes and final Division placements, records authorized exceptional rulings, and rejects, corrects, or voids them.
- A Tabulator cannot change rule versions, resolve an institutional ambiguity, approve a contest outcome or final Division placement, or edit championship ledger totals.
- Public users receive only sanitized published schedules, unofficial performance, and approved results under `docs/specs/09-public-scoreboard.md`.

## Workflow

1. Admin drafts the Athletics Competition family, candidate Men and Women score-bearing Divisions, track, field, and relay disciplines, stages, units, precision, qualification count or standard, proposal constraints, and frozen point rules. Activation remains blocked until institutional format and Men/Women treatment are recorded.
2. Admin or authorized staff records eligible entries and the approved heat or attempt order.
3. Tabulator records track times, field distances, relay times, and statuses against an expected server revision.
4. The server validates units and precision and derives stage rankings: lowest valid time first or highest valid distance first.
5. Qualification rules advance entries to the next stage only after the relevant contest outcome is approved.
6. Final discipline placement is derived from approved final contest outcomes, unless an authorized unfinished-final ruling explicitly selects time trials or semifinals as the source.
7. Approved played discipline placements award the frozen individual or relay sub-points to delegation entries.
8. The server aggregates eligible sub-points according to the frozen, institutionally confirmed Men/Women rule and derives final Division placement; unresolved ties block submission or approval.
9. Admin separately approves the final Division placement. Placement approval atomically creates Major championship ledger entries `25/20/15/5` for eligible placements exactly once; discipline contest-outcome approval creates none.
10. Corrections preserve original measurements, stage and aggregate revisions, rulings, and ledger history.

## Domain/Data Requirements

- The 2025 initial template must support track disciplines recorded in the proposal: 100m, 200m, 400m, 800m, 1500m, and 3000m for Men and Women.
- It must support field disciplines recorded in the proposal: shot put and discus for Men and Women, plus long jump and triple jump only after their proposal `TBA` division details are confirmed.
- It must support the proposal's two Men and Women relay disciplines. The printed notation `400m x 100m` and `400m x 400m` must be clarified before canonical discipline names are seeded.
- The proposal roster table supplies 10 Men and 10 Women athletes per delegation for Athletics. Whether `10` is an exact roster size or maximum, and how alternates are treated, must be confirmed before activation.
- A roster rule must enforce the proposal limits of at most three individual disciplines and two relays per athlete, and at most two athletes from one delegation in an individual discipline.
- A qualified relay may record no more than two runner changes after qualification. The exact baseline roster, whether the limit is per relay or delegation, and whether replacements must come from the registered 10-athlete Division roster are open operational interpretations that must be frozen and tested before activation.
- Shot put implement weights are Women `4 kg` and Men `7.26 kg`; discus implement weights are Women `1 kg` and Men `2.0 kg`. A field performance must record the verified implement class and weight used.
- The proposal's 3000m overlap-removal rule says that when the lead runner overlaps runners, overlapped runners are removed until only six runners remain on the track. Because `overlaps`, removal order, placement/status, timing, and application when six or fewer start are undefined, the exact executable rule must be institutionally confirmed, frozen, and tested before 3000m activation.
- A discipline rule must identify its score-bearing Division, family (`track`, `field`, or `relay`), performance type, canonical unit, accepted input unit, fixed storage and display precision, sort direction, stages, qualification rule, attempt rule where applicable, tie-breaker, and sub-point version.
- Time values must use a fixed integer representation at the configured precision, such as milliseconds, rather than binary floating point. Distance values must use a fixed scaled integer representation, such as millimeters, with explicit display precision.
- A performance must identify discipline, stage, heat or attempt, entry, value and unit, status, wind or equipment metadata only where required, recorder, timestamps, rule version, and revision.
- Supported statuses must distinguish at least valid, did not start, did not finish, no mark, disqualified, withdrawn, cancelled, and unplayed. Status eligibility for placement and participation points is rule-driven.
- An Official Contest Outcome for a discipline must reference the authoritative source performances, placement revision, tie resolution, approval, and any exceptional ruling.
- The individual sub-point mapping is first `5`, second `4`, third `3`, and proposal participation/fourth effect `1`. The relay mapping is first `10`, second `8`, third `6`, and proposal participation/fourth effect `2`. The exact participation eligibility is unresolved.
- Division aggregate totals must be derived from committed approved discipline sub-point records included by the frozen rule, including signed correction records where the sub-point model uses reversal and replacement, not manually entered.
- The Major placement-point version maps Champion `25`, 1st Runner-Up `20`, 2nd Runner-Up `15`, and eligible Participation `5`.
- The aggregate report must show each played discipline, stage source, Official Contest Outcome and approval, placement, status, sub-point effect, Men/Women subtotal as configured, aggregate total, tie resolution, separately approved final Division placement, and Major point effect.

## Invariants

- Valid track and relay performances rank ascending; valid field performances rank descending.
- Values with different units are normalized before comparison and retain the original entered unit for audit.
- Fixed precision and comparison rules are frozen before scoring begins.
- Athletics cannot activate until an authorized institutional decision confirms its competition format, whether Men and Women are separate score-bearing Divisions or inputs to another Division, and where Major championship points are awarded.
- Roster and entry limits, relay-change interpretation, implement weights, and the exact 3000m overlap-removal behavior are frozen and testable before affected scoring begins.
- Preliminary performance does not award championship points.
- A discipline awards sub-points only from an approved final discipline placement.
- An unplayed or cancelled discipline awards no sub-points.
- An unfinished final can use time-trial or semifinal results only after an authorized, recorded ruling.
- Sub-points affect only Athletics final Division ranking and never enter the championship ledger directly.
- Discipline contest-outcome approval creates no championship ledger entry; only separate approval of final Division placement creates Major championship ledger entries.
- At any cutoff, each delegation's effective championship total is `SUM(amount)` over every committed signed ledger entry at or before that cutoff, including awards, reversals, and replacements; no ledger-status filter applies.
- An unresolved discipline or aggregate tie blocks affected placement approval.
- A disqualified entry cannot receive a placement unless the frozen rule and authorized ruling expressly define otherwise.
- Corrections and voids are append-only and recalculate downstream aggregate and ledger effects transactionally.

## Edge and Failure Cases

- Unsupported unit or excess precision: reject the input and identify the required unit and precision.
- Equal measured values: retain a tie until the configured athletics tie-breaker or authorized ruling resolves placement; do not use entry order as a hidden tie-breaker.
- Did not start, did not finish, no mark, withdrawal, or disqualification: preserve the status and exclude the performance from normal value ranking; participation-point treatment remains rule-driven.
- Final not held: do not automatically promote preliminary results. Require a ruling that identifies the cause, authority, source stage, affected disciplines, and timestamp.
- Discipline not played: show it as unplayed and award zero sub-points rather than fabricating participation.
- Weather or venue interruption: retain recorded attempts and stage state; rescheduling does not alter the rule version.
- Equipment unavailable: Admin records cancellation or an authorized replacement ruling; no points are awarded for an unplayed discipline.
- Missing qualifier or incomplete final field: block final submission until an authorized status or ruling accounts for each required finalist.
- Revoked Tabulator assignment: reject new measurements immediately, including replayed commands.
- Concurrent updates: reject a stale revision and require reload; never choose the latest client timestamp as authoritative.
- Discipline correction after aggregate approval: reverse and replace affected sub-points, aggregate revision, and Major ledger entries in one audited workflow.
- Aggregate tie: block Major point approval until the frozen tie-breaker or institutional ruling is recorded.
- Roster exceeds 10 Men or 10 Women, an athlete exceeds three individual disciplines or two relays, or a delegation enters more than two athletes in one individual discipline: reject the roster or entry before scoring.
- More than two relay runner changes are requested after qualification: reject the change and retain the last valid qualified roster.
- Shot put or discus implement weight is missing or does not match the frozen Men/Women rule: reject the performance as valid until corrected.
- A 3000m overlap occurs before the operational meaning and removal procedure are confirmed: pause the affected contest; do not infer removals, placements, or statuses.

## Functional Requirements

| ID | Requirement |
|---|---|
| ATH-FR-001 | Admin can configure versioned Athletics Men and Women Divisions and their track, field, and relay disciplines, but cannot activate them until format and Men/Women aggregation are confirmed. |
| ATH-FR-002 | Each discipline defines canonical units, accepted input units, fixed precision, and sort direction. |
| ATH-FR-003 | Tabulator can record only performances covered by an active Athletics assignment. |
| ATH-FR-004 | The server ranks valid track and relay values ascending and field values descending. |
| ATH-FR-005 | The system supports qualification stages and advances only from approved discipline contest outcomes. |
| ATH-FR-006 | The system records non-performance statuses without converting them to fabricated measurements. |
| ATH-FR-007 | Approved individual discipline placements use the versioned `5/4/3/1` sub-point mapping. |
| ATH-FR-008 | Approved relay discipline placements use the versioned `10/8/6/2` sub-point mapping. |
| ATH-FR-009 | Unplayed and cancelled disciplines award no sub-points. |
| ATH-FR-010 | An unfinished final uses time-trial or semifinal results only through an authorized recorded ruling. |
| ATH-FR-011 | The system derives Men and Women subtotals and final Division totals from approved sub-points using only the frozen, institutionally confirmed aggregation rule. |
| ATH-FR-012 | The system blocks unresolved discipline and aggregate ties from affected approval. |
| ATH-FR-013 | Admin can separately review and approve discipline Official Contest Outcomes and final Division placement. |
| ATH-FR-014 | Final Division placement approval creates versioned Major `25/20/15/5` championship ledger entries exactly once; contest-outcome approval creates none. |
| ATH-FR-015 | Corrections preserve measurements and rulings and replace downstream sub-point, aggregate, and ledger effects. |
| ATH-FR-016 | Admin can generate a reproducible Athletics discipline, sub-point, aggregate, and Major-point report. |
| ATH-FR-017 | The system enforces the frozen 10 Men and 10 Women roster interpretation, athlete discipline limits, delegation entry limits, and post-qualification relay-change limit. |
| ATH-FR-018 | The system validates shot put and discus implement weights and blocks 3000m scoring until the overlap-removal rule is operationally confirmed. |

## Acceptance Criteria

- A lower valid 100m time ranks ahead of a higher time, while a longer valid shot-put distance ranks ahead of a shorter distance.
- Athletics activation fails while either the Competition format or Men/Women Division aggregation and championship-point treatment is unconfirmed.
- Equivalent values entered in accepted units normalize to the same authoritative measurement and do not lose configured precision.
- A preliminary result cannot award discipline sub-points or populate the championship ledger.
- Approved individual placements produce `5/4/3/1`; approved relay placements produce `10/8/6/2` under the frozen 2025 template.
- An unplayed discipline visibly contributes zero to every delegation.
- The system refuses to use semifinal or time-trial rankings for an unfinished final until an Admin records the authorized ruling.
- A tie or disqualification is visible and cannot be silently resolved by database order.
- Division aggregate totals equal the sum of committed approved signed sub-point amounts included by the frozen aggregation rule.
- Approval of a discipline contest outcome can award sub-points but no championship points; separate approval of final Division placement produces `25/20/15/5` Major point effects exactly once and never posts discipline sub-points to the championship ledger.
- A roster and entry matrix exercises 10 Men and 10 Women per delegation, three individual disciplines and two relays per athlete, two athletes per delegation per individual discipline, and rejects each one-over-limit case.
- A qualified relay accepts up to two valid runner changes and rejects a third under the frozen interpretation.
- Women's and Men's shot put accept only `4 kg` and `7.26 kg` implements respectively; Women's and Men's discus accept only `1 kg` and `2.0 kg` respectively.
- Table-driven 3000m tests cover no overlap, one or multiple overlaps, exactly six remaining, six or fewer starters, status and placement effects, and timing only after the institution approves each operational interpretation.
- Correcting an approved discipline produces a traceable replacement aggregate and compensating Major ledger entries where placement changes.

## Testing

- Domain tests for unit normalization, scaled-integer precision, ascending and descending sorts, status eligibility, qualification, roster and entry constraints, relay changes, implement weights, the confirmed 3000m overlap procedure, individual and relay mappings, aggregate totals, ties, and Major conversion.
- Feature tests for assignment scope, rule freezing, discipline submission, Admin ruling, separate contest-outcome and final Division placement approval, duplicate approval, rejection, correction, and void.
- PostgreSQL tests for concurrent measurement revisions, atomic final Division placement approval and ledger creation, and downstream correction locking.
- Property or table-driven tests covering each supported discipline family and boundary precision.
- Browser tests for mobile measurement entry, explicit units, status entry, stale revisions, tie warnings, unfinished-final ruling, and aggregate review.
- User acceptance simulation with the Sports Coordinator, Athletics tournament manager, Tabulators, and Admin using proposal-derived Men, Women, and relay examples.

## Decision Register

Athletics blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 35-47. This specification remains the technical contract for measurements, qualification, Sub-Points, aggregation, and precision after the format and participation rules are approved.
