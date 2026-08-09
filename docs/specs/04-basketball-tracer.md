# Basketball Tracer Specification

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This is the first end-to-end team-sport tracer under the [product and domain contract](../cspc-siklab-plan.md). It applies the identity boundary in `01-identity-and-rbac.md`, the three scoring layers and Major template in `02-competition-scoring-rules.md`, and the single-elimination structure in `03-tournament-bracketing.md` without duplicating their unrelated requirements.

Source priority follows the product contract:

1. Product-owner decisions recorded in the product contract and decision register.
2. `docs/Approved-2025-Intramurals-Proposal.pdf`, especially pages 3, 10, and 11, for Basketball Men/Women, roster limits, single elimination, house rules, and Major points.
3. `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` for real-time score entry, automated bracket updates, public transparency, reports, and black-box testing.
4. `docs/SYNTIX.docx` for Admin setup, Tabulator live scoring, final submission, and public viewing.
5. The repository for the Laravel 13, Inertia/React, PostgreSQL, Reverb, and PWA stack.

The proposal's FIBA fallback and referee-finality language means SYNTIX records authorized decisions; it does not replace referees or operate as the official game clock unless a later approved implementation explicitly does so.

## Problem

Basketball provides the smallest meaningful vertical slice that proves closed authorization, explicit Tabulator assignment, bracket generation, live unofficial scoring, result submission, Admin approval, approved advancement, final Division placement, championship ledger creation, public updates, and auditability. The slice must remain operationally honest: the first release records score and period context but does not claim to enforce the physical game clock, referee calls, timeout allocation, fouls, or full FIBA rules.

## Goals

- Configure one canonical Basketball Competition family with Men and Women as separate score-bearing Divisions.
- Enforce a maximum of 15 student-athletes per delegation entry.
- Record the approved draw and generate a single-elimination bracket with deterministic BYEs.
- Let an assigned Tabulator record a live unofficial score and period/overtime context.
- Preserve proposal-derived timing rules as frozen operational metadata.
- Submit a completed match result for Admin approval.
- Advance the bracket only after approval.
- Determine Champion, 1st Runner-Up, and 2nd Runner-Up through the final and third-place playoff.
- Award Major championship points only from separate approval of each final Division placement.
- Preserve referee and tournament-manager authority.
- Deliver a minimal first vertical slice before adding optional basketball statistics or offline mutation sync.

## Non-Goals

- An authoritative game clock, shot clock, horn, scoreboard hardware integration, or automatic timeout timing in the first slice.
- Play-by-play, possession, fouls, free throws, player substitutions, individual statistics, lineups, or analytics.
- Referee assignment, referee decision automation, video review, or protest adjudication.
- Automatic drawing of lots or seeding.
- Public participant registration, eligibility-document uploads, or medical data storage.
- Offline score-command synchronization in the first online-authoritative slice.
- Other sports, double elimination, round robin, judged scoring, or Athletics aggregation.

## Users and Authorization

- Admin configures Basketball Men/Women, frozen rules, entries, rosters, draw, bracket, schedule, venue, and Tabulator assignments.
- Admin reviews submitted match results and approves or rejects with a reason.
- Tabulator views and mutates only Basketball contests covered by an active assignment and cannot edit rules, rosters, brackets, final Division placement, or ledger entries.
- One Tabulator may be assigned to both Basketball divisions through two explicit assignments.
- An unassigned Tabulator is denied even if they hold a Tabulator role in the same event.
- Public anonymously views the sanitized published bracket, live unofficial score, approved results, and official placement or standings.
- Referees and tournament managers remain authoritative for play, score decisions, forfeits, overtime progression, and rules not covered by the house rules. An authorized scorer or Admin records their decisions.

The approval conflict-of-duty policy remains open as defined in `01-identity-and-rbac.md`; recommended behavior is that the submitter cannot approve their own result.

## Workflow

### Setup

1. Admin creates the canonical Basketball Competition family and enables its Men and/or Women score-bearing Divisions, each using single elimination and its own Major point-template binding.
2. Admin creates one delegation entry per participating delegation and encodes up to 15 student-athletes plus allowed bench roles.
3. Admin records roster and institutional eligibility status without uploading medical certificates or health records.
4. Admin locks eligible entries and records the organizer-approved draw order.
5. The system generates the deterministic next-power-of-two bracket and BYE resolutions defined by `03-tournament-bracketing.md`.
6. Admin reviews and publishes the bracket.
7. Admin schedules contests and assigns one or more Tabulators to exact Basketball scopes.

### Live Match

1. Assigned Tabulator opens an authorized contest and confirms both entries, schedule, venue, and frozen operational rules.
2. Tabulator starts the contest, changing it from `draft` to `live`.
3. Tabulator records current team totals and current phase: quarter 1-4, overtime 1, overtime 2, or sudden-death first-to-score.
4. Each mutation carries an idempotency key and expected revision.
5. The server validates nonnegative integer totals, legal phase transitions, authorization, and current revision.
6. Sanitized updates broadcast after commit and display publicly as `Unofficial` with a last-updated timestamp.
7. Realtime failure does not block normal authorized HTTP updates or public refresh.

### Completion, Submission, and Approval

1. The referee or authorized officials determine the actual final score and winner under the house rules and applicable FIBA fallback.
2. Tabulator records the fields required by the outcome matrix for `played`, `walkover`, `forfeit`, `no_show`, `withdrawal`, or `disqualification`.
3. The system validates that a played game has a non-tied final outcome. It does not infer the official winner against a contradictory referee ruling.
4. Tabulator marks the contest `completed`, reviews the snapshot, and submits it.
5. `submitted` locks the snapshot against scorer edits.
6. Admin reviews the score, outcome, frozen metadata, bracket target, submitter, and audit history.
7. Admin approves or rejects with a reason.
8. Approval records the official match result and applies its explicit `advance_winner` or `no_advancement` disposition in one transaction.
9. Rejection returns a correctable revision without destroying the submitted snapshot.

### Final Placement and Championship Points

1. Approved semifinal outcomes populate the final and third-place playoff.
2. Approved final outcome determines Champion and 1st Runner-Up candidates.
3. Approved third-place playoff determines the 2nd Runner-Up candidate.
4. Admin separately approves the complete final Division placement.
5. Final Division-placement approval creates Major ledger entries of 25, 20, and 15 for the placed delegations and 5 only for entries that satisfy the institutionally approved participation policy.
6. Match wins, live totals, BYEs, advancement, and preliminary approvals create no championship ledger entry.

## Domain/Data Requirements

### Competition and Roster

- Competition family: one event-scoped `Basketball` Competition with no family-level placement or ledger award.
- Score-bearing Divisions: `Men` and `Women`, each with team mode, single elimination, its own governing rule version, final Division placement, and Major point award.
- One event-scoped delegation entry per Basketball Division unless CSPC explicitly configures otherwise.
- Maximum 15 student-athletes per Basketball Men entry and 15 per Basketball Women entry.
- Proposal bench context: up to 1 student coach and 2 faculty coaches per division entry.
- Entry-member role distinguishes student-athlete, student coach, and faculty coach.
- Only rostered players, faculty coach(es), and student coach are eligible bench occupants under the proposal; uniform compliance is an official operational decision, not an automated vision check.
- Eligibility status is stored; medical certificates, consent forms, and health details are not.

### Proposal-Derived Operational Rules

The frozen Basketball rule version stores:

| Rule | 2025 proposal value | First-slice behavior |
|---|---|---|
| Format | Single elimination | Authoritative bracket behavior |
| Regulation | 4 quarters of 10 minutes | Display and validation metadata |
| Timeout duration | 30 seconds | Display metadata; no timer enforcement |
| Quarters 1-3 | Running time, except timeouts and free throws | Operational metadata only |
| Quarter 4 | Stop time on dead-ball situations | Operational metadata only |
| First overtime | 5 minutes | Phase and duration metadata |
| Second overtime | 3 minutes | Phase and duration metadata |
| Third overtime | First team to score | Sudden-death phase metadata |
| Other rules | Revert to FIBA where house rules are silent | Human official authority |

The first slice may record an optional elapsed/remaining display value supplied by the Tabulator, but it must be labeled non-authoritative and cannot end a period, award a timeout, or decide a result. Do not market the application as the game clock.

### Score State

Minimum mutable live state:

- Contest ID and revision.
- Home/slot-A entry and away/slot-B entry.
- Nonnegative integer total for each entry.
- Phase: pregame, Q1, Q2, Q3, Q4, OT1, OT2, sudden death, final.
- Live status and last authoritative server update timestamp.
- Optional operational note visible only to authorized users.
- Idempotency key and actor for every mutation.

Minimum submitted result snapshot:

- Final totals when allowed or required by the outcome matrix.
- Winning and losing entry when required by the outcome matrix.
- Outcome type: `played`, `walkover`, `forfeit`, `no_show`, `withdrawal`, or `disqualification` as institutionally confirmed.
- Advancement disposition: `advance_winner` or `no_advancement`.
- Terminal phase and overtime sequence used.
- Referee/tournament ruling note or reference when the outcome is not a normal played result or when score and winner need explanation.
- Submitter, submission timestamp, base revision, frozen Division rule version, and point-template version.

### Outcome Validation Matrix

`Required` fields must be present, `Forbidden` scores must be null rather than synthetic zeroes, and `Conditional` fields follow the stated condition. Every approved outcome requires an explicit advancement disposition; the bracket never infers advancement merely from outcome type.

| Outcome type | Final scores | Winner | Authorized ruling | Advancement disposition | Participation points |
|---|---|---|---|---|---|
| `played` | Required for both entries; nonnegative integers | Required; must match the higher total unless the ruling documents an exceptional official decision | Optional normally; required when winner and totals conflict | Required; `advance_winner` | Open institutional policy; do not infer eligibility |
| `walkover` | Forbidden | Required | Required, including authority and reason | Required; `advance_winner` | Open institutional policy; do not infer eligibility |
| `forfeit` | Conditional: optional only when play occurred, otherwise null; never used to infer the ruling | Required for a one-sided forfeit; null only for a documented both-side ruling | Required, identifying forfeiting side or both sides | Required; `advance_winner` for one side or `no_advancement` for both sides | Open institutional policy; do not infer eligibility |
| `no_show` | Forbidden | Required for a one-sided no-show; null only when both sides are ruled absent | Required, identifying absent side or both sides | Required; `advance_winner` for one side or `no_advancement` for both sides | Open institutional policy; do not infer eligibility |
| `withdrawal` | Conditional: optional only when play began, otherwise null; never used to infer the ruling | Required when the opponent advances; otherwise null | Required, including timing and withdrawing side | Required; `advance_winner` or `no_advancement` as ruled | Open institutional policy; do not infer eligibility |
| `disqualification` | Conditional: optional only when play began, otherwise null; never used to infer the ruling | Required when the opponent advances; otherwise null | Required, including disqualified side and authority | Required; `advance_winner` or `no_advancement` as ruled | Open institutional policy; do not infer eligibility |

An approved `advance_winner` outcome must name a winner. An approved `no_advancement` outcome must have no winner and leaves downstream routing to the authorized bracket-resolution workflow. Scores retained for a mid-game forfeit, withdrawal, or disqualification are observational only. No outcome type settles participation-point eligibility until CSPC approves that separate policy.

### Bracket and Placement

Use the Tournament, Bracket Version, Bracket Node, Bracket Slot, Advancement Rule, and Draw Record direction from `03-tournament-bracketing.md`.

- BYEs auto-advance without a contest result.
- Approved winners alone fill downstream slots.
- Semifinal loser routing fills the third-place playoff.
- Final and third-place approval supply placement candidates.
- Final Division-placement approval is distinct from match approval.

### Public Data

Public Basketball payload may include delegation display names, public team labels, bracket nodes, score totals, current phase, schedule, venue, status, `Unofficial` label, last update, and approved result/placement.

It excludes student numbers, private roster fields, eligibility notes, bench compliance notes, Tabulator identity unless explicitly approved, internal rulings, correction reasons, assignment data, and audit logs.

### Minimal First Vertical Slice

The first implementation includes only:

1. One seeded event with the Basketball Competition and Men Division enabled; the Women Division uses the same configurable path and must not require a code fork.
2. Admin-created eligible delegation entries with roster-count validation.
3. Admin-recorded draw, deterministic single-elimination preview, publication, BYEs, final, and third-place node.
4. Explicit Tabulator assignment and denial of unassigned access.
5. Server-authoritative current team totals and phase with revision and idempotency checks.
6. Public sanitized live score labeled unofficial with HTTP refresh fallback.
7. Completed result submission and Admin approve/reject.
8. Approved bracket advancement.
9. Approved final Division placement and exactly-once Major ledger awards.
10. Audit records for draw, publication, assignment, score mutations, submission, approval, advancement, and ledger creation.

### Deferred Features

- Offline command outbox and synchronization.
- Authoritative game clock, shot clock, timeout countdown, horn, and hardware integration.
- Play-by-play event log and score undo timeline beyond required audit/revision history.
- Team fouls, player fouls, free throws, possession arrow, substitutions, lineups, and individual statistics.
- Referee management, digital signatures, protest workflow, and video evidence.
- Automated schedule succession and warm-up or venue-clearance timers.
- Public roster names pending institutional privacy approval.
- Basketball reports beyond the minimum bracket/result and championship ledger evidence needed by the tracer.

## Invariants

- Basketball is one canonical Competition family; Men and Women are separate score-bearing Divisions, and each entry has at most 15 student-athletes.
- An active Tabulator role without a matching assignment grants no Basketball access.
- Live score is unofficial and does not affect official standings.
- Scores are nonnegative integers and updates require the expected contest revision and idempotency key.
- Submitted results are locked snapshots.
- A played final result cannot remain tied; referee authority or an authorized ruling resolves the game.
- Only an Admin-approved contest outcome with `advance_winner` may route its named winner; a BYE remains the separate automatic-resolution case.
- A BYE is not a played result and creates no score, win, loss, or championship points.
- The final and third-place playoff determine placement candidates; final Division placement requires separate approval.
- Only separate approval of final Division placement creates Major championship points for that Division.
- Every official outcome satisfies the score, winner, ruling, and advancement matrix; none implies participation-point eligibility.
- Repeating submission, approval, advancement, or placement approval cannot duplicate effects.
- Timing rules are operational metadata until an authoritative clock is explicitly approved and implemented.
- SYNTIX never overrides a referee or tournament-manager ruling.
- Private roster and operational data never enter public broadcasts or public caches.

## Edge and Failure Cases

- More than 15 student-athletes: block roster readiness and bracket eligibility.
- Missing or ineligible entry: exclude before draw lock; do not silently remove after publication.
- Zero or one eligible entry: follow blocked/uncontested ruling behavior in the bracket specification.
- Non-power-of-two entries: calculate and visibly resolve deterministic BYEs.
- Team withdraws before/after publication, arrives late, does not appear, forfeits, or is disqualified: require authorized status confirmation and preserve topology.
- Both teams unavailable: leave node unresolved pending Admin-recorded ruling.
- A non-played outcome contains synthetic zero scores: reject it when scores are forbidden; preserve optional mid-game scores only for the outcome types that allow them.
- A ruled outcome omits its ruling or advancement disposition, or names a winner with `no_advancement`: reject submission or approval.
- Tied score after Q4: permit OT1 metadata; if still tied, OT2; if still tied, sudden death first-to-score.
- Tabulator skips from Q4 to final with a tie: reject.
- Tabulator enters a lower corrected live total: allow only as a revision-aware score correction and audit it; do not model blind decrement buttons as untraceable actions.
- Two devices update the same contest: accept the first valid expected revision and reject the stale update for review.
- Duplicate mutation or submission: return the original authoritative response without applying twice.
- Assignment revoked mid-game: reject new mutations; an Admin must assign another Tabulator.
- Realtime unavailable: retain HTTP mutation/read behavior and mark public data freshness.
- Network fails before server acknowledgement: client retries with the same idempotency key.
- Admin rejects result: preserve snapshot and reason, return an editable correction revision to an authorized scorer.
- Approved result corrected after downstream contest starts: use protected downstream correction behavior from the bracket specification.
- Competition cancelled: preserve bracket/results and reverse official point effects if any.
- Referee declares a winner inconsistent with entered totals because of an exceptional ruling: require outcome type and documented authorized ruling; do not silently infer or alter it.

## Functional Requirements

| ID | Requirement |
|---|---|
| BTR-001 | Admin shall configure one Basketball Competition with Men and Women as separate score-bearing team Divisions using single elimination. |
| BTR-002 | The system shall enforce a maximum of 15 student-athletes per Basketball division entry. |
| BTR-003 | The roster model shall distinguish student-athletes, one student coach, and up to two faculty coaches for bench context. |
| BTR-004 | Admin shall record the approved draw and generate/publish a deterministic next-power-of-two bracket with valid BYEs. |
| BTR-005 | The bracket shall include a final and a third-place playoff whenever two semifinal losers exist. |
| BTR-006 | The frozen rule version shall store four 10-minute quarters, 30-second timeouts, running/stop-time metadata, and the 5-minute, 3-minute, first-to-score overtime sequence. |
| BTR-007 | The first slice shall treat timing rules as operational metadata and shall not claim an authoritative game clock. |
| BTR-008 | An assigned Tabulator shall record live integer team totals and phase using idempotency keys and expected revisions. |
| BTR-009 | The system shall deny unassigned Tabulators and immediately enforce assignment revocation. |
| BTR-010 | Public live Basketball data shall be sanitized, timestamped, and labeled unofficial. |
| BTR-011 | Tabulator shall complete, review, and submit a locked final result snapshot. |
| BTR-012 | Admin shall approve or reject the submitted result with an auditable reason where applicable. |
| BTR-013 | Only an approved match outcome shall advance the bracket. |
| BTR-014 | Approved semifinal outcomes shall populate both the final and third-place playoff routes. |
| BTR-015 | Approved final and third-place outcomes shall produce Champion, 1st Runner-Up, and 2nd Runner-Up placement candidates. |
| BTR-016 | Only separate approval of final Division placement shall create that Division's Major championship ledger entries. |
| BTR-017 | Match scores, wins, BYEs, and advancement shall create no delegation championship points. |
| BTR-018 | The system shall preserve referee and tournament-manager authority through recorded outcomes and authorized rulings. |
| BTR-019 | Realtime broadcasts shall occur only after commit and normal HTTP reads/writes shall remain available as fallback. |
| BTR-020 | Corrections shall preserve original results, protect downstream contests, and reverse/replace ledger effects only when final Division placement changes. |
| BTR-021 | The system shall validate `played`, `walkover`, `forfeit`, `no_show`, `withdrawal`, and `disqualification` against the required score, winner, ruling, and advancement fields in this specification. |
| BTR-022 | Basketball outcome approval shall not infer participation-point eligibility; only the separately approved participation policy used at final Division placement may award participation points. |

## Acceptance Criteria

- Admin can create one Basketball Competition with Men and Women Divisions through the same configuration path and no Division-specific code fork.
- A roster with 15 student-athletes passes; a roster with 16 cannot become eligible.
- Bench roles can record at most one student coach and two faculty coaches without counting them as student-athletes.
- Seven eligible entries generate an eight-slot bracket with one auditable BYE and no fake result.
- A Tabulator assigned to Basketball Men can update it and is denied Basketball Women unless separately assigned.
- Live score changes leave official placement and overall standings unchanged and appear publicly as unofficial with a timestamp.
- A stale update is rejected; replaying the same idempotency key does not increment a score twice.
- Q4 tied completion is rejected until the recorded phase follows OT1, OT2 if needed, and sudden death if needed, or an authorized ruling is recorded.
- No application behavior claims to start, stop, or expire the official game clock in the first slice.
- Submission locks the snapshot; rejection preserves it and provides a reason for correction.
- An unapproved result does not advance; approval with `advance_winner` advances exactly once, while `no_advancement` leaves downstream winner slots unfilled.
- Each matrix-valid outcome accepts exactly its permitted fields; missing required fields, forbidden scores, winner/disposition contradictions, and undocumented non-played outcomes are rejected.
- Approved semifinals populate the final and third-place playoff.
- Approved final and third-place outcomes support the top three placement candidates.
- Approving a preliminary match creates zero championship ledger entries.
- Approving a final Major Division placement creates 25, 20, and 15 points exactly once for that Division, plus only institutionally eligible participation awards.
- Public payloads contain no private roster, eligibility, assignment, ruling, correction, or audit data.
- If Reverb is unavailable, authorized HTTP scoring and public HTTP refresh continue to work.
- A correction affecting a started downstream contest is blocked from silent rewrite.

## Testing

### Domain Tests

- Roster count and bench-role limits.
- Legal phase transitions through Q1-Q4, OT1, OT2, sudden death, and final.
- Nonnegative integer score validation, ties, winner consistency, and exceptional ruling requirements.
- Full outcome-matrix validation for required, forbidden, and conditional scores; winner; ruling; advancement disposition; both-side outcomes; and no participation inference.
- Major point mapping and proof that match approval creates no ledger entry.
- Exactly-once final Division-placement awards and correction reversals.

### Feature and PostgreSQL Tests

- Admin setup, draw, seven-entry bracket, BYE, publication, final, and third-place nodes.
- Role plus assignment authorization, multiple assignments, and revocation during an open match.
- Revision conflict between two scoring clients.
- Idempotent score, submission, match approval, advancement, and placement approval.
- Transactional match approval and downstream slot population.
- Transactional final Division-placement approval and ledger creation.
- Rejection, resubmission, correction, downstream protection, and cancellation.

### Realtime, Browser, and Public Tests

- Broadcast only after database commit.
- Mobile-width live scoring with clear teams, totals, phase, connection state, and submission confirmation.
- Public unofficial label, freshness timestamp, disconnected/stale state, and HTTP fallback.
- No authenticated Inertia response or private roster data in service-worker caches.
- Keyboard and touch operation for score changes and confirmation; controls have clear accessible names.

### Black-Box and User Acceptance Tests

- Simulate a complete Basketball Men bracket with tournament manager, referee representative, Tabulator, Admin, and public viewer.
- Include a BYE, normal regulation result, overtime result, rejected submission, assignment revocation, network/realtime interruption, final, third-place playoff, and corrected result.
- Compare official paper/referee result with submitted and approved SYNTIX records.
- Confirm with officials that clock metadata never appears to overrule the physical official clock.

## Decision Register

Basketball and tournament blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 24-34. This specification remains the tracer contract for the behavior that can be activated once the sport-specific decisions are approved.
