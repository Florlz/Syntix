# Tournament Bracketing Specification

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification is the detailed tournament contract under the [product and domain contract](../cspc-siklab-plan.md). It relies on `01-identity-and-rbac.md` for account and assignment enforcement, `02-competition-scoring-rules.md` for scoring layers and championship ledger rules, and `04-basketball-tracer.md` for the first single-elimination implementation slice.

Source priority follows the product contract:

1. Product-owner decisions recorded in the product contract, including the universal requirement that an Admin records the organizer-approved draw and SYNTIX never conducts it.
2. `docs/Approved-2025-Intramurals-Proposal.pdf`, especially pages 10-16, for 2025 formats and sport authority. Any shown draw, bracket, or succession is an example, not a universal generation algorithm or routing map.
3. `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` for tournament brackets, adaptive ranking, integrity, and testing objectives.
4. `docs/SYNTIX.docx` for Admin bracket setup, Tabulator finalization, and public bracket viewing.
5. The repository for the authoritative stack.

## Problem

Tournament brackets must translate an approved organizer draw into a valid, reproducible structure while handling non-power-of-two entry counts, operational disruptions, approval, corrections, and public viewing. A bracket that advances from unapproved scores, silently reseeds after publication, fabricates BYE results, or rewrites downstream matches would undermine referee and tournament-manager authority and could create incorrect championship points.

## Goals

- For every tournament format, auto-place or schedule eligible entries from the Admin-recorded organizer-approved draw; SYNTIX does not conduct the draw.
- Generate deterministic single-elimination, double-elimination, and round-robin structures.
- Calculate the next power-of-two bracket size and resolve BYEs without fake contests.
- Handle zero-entry and one-entry competitions safely.
- Include a third-place playoff in single elimination.
- Enforce an always-reset rule when the undefeated finalist first loses in double elimination.
- Represent odd round-robin rest slots without fake played BYEs.
- Advance only from approved outcomes.
- Preserve published topology, corrections, downstream history, and auditability.
- Keep match outcomes separate from final championship-point awards.
- Expose only sanitized published bracket data publicly.

## Non-Goals

- Automatic drawing of lots, random seeding, or replacing organizer approval.
- Forcing Athletics into a knockout bracket while its proposal format remains disputed.
- Encoding every sport's internal scoring rules.
- Awarding championship points per match, win, BYE, or advancement.
- Automatically adjudicating withdrawals, forfeits, disqualifications, protests, or tied knockout outcomes.
- Retrofitting double elimination before the single-elimination Basketball tracer is proven.

## Users and Authorization

- Admin locks eligible entries, records the approved draw order, generates and reviews a preview, publishes a bracket, and authorizes corrections or rulings.
- Tabulator views and scores only contests covered by an active `competition_division` or exact `contest` assignment.
- Tabulator cannot generate, publish, unpublish, reseed, manually advance, or correct bracket topology.
- Judge receives no bracket mutation permission merely from the Judge role.
- Public anonymously views sanitized published brackets only.
- Tournament managers, referees, and committees retain real-world decision authority; an authorized Admin records their rulings in SYNTIX.

## Workflow

### Generation and Publication

```text
Eligible entries locked
    -> Admin records approved draw order
    -> System calculates structure and BYEs/rests
    -> System auto-places entries into a deterministic preview
    -> Admin reviews the preview
    -> Admin publishes the bracket
    -> Published bracket version becomes immutable
```

1. The eligible pool excludes duplicates and ineligible entries.
2. The Admin records the organizer-approved draw outcome and confirmation metadata. This universal product workflow applies even when the proposal shows no activity-specific draw example.
3. For elimination formats, the system calculates size and BYEs and constructs all routing nodes.
4. The same eligible pool, draw order, format rule version, and generation algorithm version always produce the same preview.
5. The Admin publishes only after validating the preview.
6. Publication freezes the bracket's nodes, edges, and draw-derived topology. Slot resolutions and node lifecycle statuses may then change only through automatic BYE resolution, approved outcomes, or approved corrections.

### Contest Advancement

1. A Tabulator records performance and completes a contest under an active assignment.
2. The Tabulator submits a locked result snapshot.
3. Admin reviews and approves or rejects the outcome.
4. Only an approved winner or other approved outcome follows the node's advancement rules.
5. Routing updates internal bracket state but creates no championship ledger entry.
6. Approved final and third-place outcomes establish placement candidates.
7. Separate approval of the final Division placement creates configured championship points.

### Correction

1. An authorized Admin starts a draft correction with a reason and identifies the approved official outcome to replace.
2. The system computes an impact preview of every descendant node affected by winner or loser routing, but changes no slot, node resolution, or placement candidate while the correction is draft or submitted.
3. Approval is permitted automatically only when every affected descendant has not started and has no submitted result. If a descendant is live, submitted, or approved, approval is blocked until an authorized resolution is selected and included in the approval.
4. In one transaction, Admin approval creates the corrected official outcome revision and applies every approved descendant slot and node-status update. A failed transaction leaves both the official outcome and all descendants unchanged.
5. The system preserves prior bracket versions and result revisions and records the approving actor and reason.
6. Signed championship ledger reversals and replacements occur only through separate final Division-placement approval if that official placement changes.

## Domain/Data Requirements

### Format Mapping

The 2025 initial configuration maps:

| Format | Competitions |
|---|---|
| Single elimination | Basketball, Volleyball, Sepak Takraw, Arnis, Taekwondo |
| Double elimination | Badminton, Table Tennis, Lawn Tennis |
| Round robin | Chess |
| Aggregate placement | Athletics disciplines and relays |
| Criteria-based placement | Literary, musical, dance, visual arts, and other judged activities |
| Custom series | Esports |

The proposal also labels Athletics single elimination, but its detailed rules describe heats, measurements, qualification, and aggregate placement. Athletics must not use knockout generation without institutional clarification.

### Bracket Size and BYEs

For `n >= 2` eligible entries:

```text
bracket_size = smallest power of two greater than or equal to n
bye_count = bracket_size - n
opening_match_count = bracket_size / 2
played_opening_match_count = n - opening_match_count
```

For elimination previews, the implementation must use one documented, versioned slot-allocation algorithm that consumes Admin draw order, distributes BYEs without BYE-versus-BYE nodes, and remains deterministic. The first implementation may use this baseline allocation:

1. Create opening nodes in stable topology order.
2. Fill `played_opening_match_count` nodes with two entries each in draw order.
3. Fill each remaining opening node with one entry and one `BYE` slot in draw order.
4. Connect winners and, for double elimination, losers through the format's versioned routing map.

If CSPC requires a different BYE distribution convention, it must be approved and versioned before publication; historical versions do not change.

| Eligible entries | Bracket size | BYEs | Behavior |
|---:|---:|---:|---|
| 0 | None | None | Block generation and report no eligible entries |
| 1 | None | None | Mark uncontested and require an Admin-recorded ruling |
| 2 | 2 | 0 | One opening contest |
| 3 | 4 | 1 | One played opening node and one BYE resolution |
| 5 | 8 | 3 | One played opening node and three BYE resolutions |
| 7 | 8 | 1 | Three played opening nodes and one BYE resolution |
| 8 | 8 | 0 | Four played opening nodes |

A BYE is an automatic bracket resolution, not a contest:

- The affected entry advances automatically.
- The empty opponent slot is visibly labeled `BYE`.
- No live score, result, match win, loss, forfeit, or contest record is fabricated.
- No championship point is created.
- Draw order, slot, resolution, and advancement remain auditable.

### Zero and One Entry

- Zero entries: generation is blocked; no bracket or placement exists.
- One entry: set the Division tournament state to `uncontested`; do not automatically declare a champion.
- The Admin records the authorized committee or tournament-manager ruling.
- A ruling may cancel the Division, approve a placement, or record another documented resolution.
- Championship points arise only if the ruling explicitly approves an eligible final Division placement under the frozen point-rule version.

### Single Elimination

- An entry advances only from an approved outcome.
- Live, completed, or submitted results do not advance.
- Two semifinal losers enter a generated third-place playoff.
- The third-place playoff is the confirmed default whenever two semifinal losers exist; no generic setting may omit it.
- The final supplies Champion and 1st Runner-Up candidates.
- The third-place playoff winner supplies the 2nd Runner-Up candidate.
- A tied result blocks advancement until the configured tie-breaker or authorized ruling resolves it.
- Venue and schedule changes do not alter topology.

### Double Elimination

- Every entry begins in the winners bracket.
- A first approved loss routes the entry to the deterministic losers-bracket node.
- A second approved loss eliminates the entry.
- A BYE creates no loss and no unnecessary losers-bracket entry.
- The winners-bracket finalist must be defeated twice.
- Every published topology contains a reset-final node in `inactive` status; publication never omits or conditionally generates it.
- If the undefeated finalist wins the first championship contest, the reset node transitions to `not_required`. If that finalist loses, the reset node transitions from `inactive` to `active` and must be played or officially ruled.
- The last required championship contest determines Champion and 1st Runner-Up. For supported sizes with at least four entries, the loser of the losers-bracket final is the 2nd Runner-Up candidate.
- Winner and loser routing must be visible, deterministic, versioned, and exhaustively tested for supported sizes.
- Before a double-elimination size can be activated or published, CSPC must sign off its complete routing map. The map identifies every winner edge, loser edge, placement source, and reset transition and explicitly proves each BYE source has no loser edge or phantom losers-bracket participant.

### Round Robin

- Generate the configured opponent schedule so every eligible entry faces every other entry as required.
- For an odd count, assign one resting entry per round; do not create a played BYE contest.
- Apply the frozen win, draw, loss, and tie-breaker rules to internal standings.
- Game wins do not enter the delegation championship total.
- Only approved final round-robin placement can create championship ledger entries.

### Exhaustive Baseline Examples

The examples use draw order `E1`, `E2`, and so on and the baseline allocation above. `N` means opening node, `S` semifinal, `F` final, and `P3` third-place playoff. A BYE resolution is automatic and is not approved as a match result.

#### Three Entries

```text
Bracket size 4; one BYE
N1: E1 vs E2 -> approved winner W1; loser L1
N2: E3 vs BYE -> E3 advances automatically
F:  W1 vs E3 -> Champion and 1st Runner-Up candidates
P3: not generated because a four-slot bracket has no two played semifinals
```

For only three entries, the authorized placement ruling determines 2nd Runner-Up because there are not two semifinal losers for a playoff. The system must not fabricate a second semifinal loser.

#### Five Entries

```text
Bracket size 8; three BYEs
N1: E1 vs E2 -> W1, L1
N2: E3 vs BYE -> E3 advances
N3: E4 vs BYE -> E4 advances
N4: E5 vs BYE -> E5 advances
S1: W1 vs E3 -> W2, L2
S2: E4 vs E5 -> W3, L3
F:  W2 vs W3 -> Champion and 1st Runner-Up candidates
P3: L2 vs L3 -> 2nd Runner-Up candidate
```

#### Seven Entries

```text
Bracket size 8; one BYE
N1: E1 vs E2 -> W1
N2: E3 vs E4 -> W2
N3: E5 vs E6 -> W3
N4: E7 vs BYE -> E7 advances
S1: W1 vs W2 -> W4, L4
S2: W3 vs E7 -> W5, L5
F:  W4 vs W5 -> Champion and 1st Runner-Up candidates
P3: L4 vs L5 -> 2nd Runner-Up candidate
```

#### Eight Entries

```text
Bracket size 8; no BYEs
N1: E1 vs E2 -> W1
N2: E3 vs E4 -> W2
N3: E5 vs E6 -> W3
N4: E7 vs E8 -> W4
S1: W1 vs W2 -> W5, L5
S2: W3 vs W4 -> W6, L6
F:  W5 vs W6 -> Champion and 1st Runner-Up candidates
P3: L5 vs L6 -> 2nd Runner-Up candidate
```

Every arrow in these examples is conditional on an approved outcome except an explicit BYE resolution.

### Data Direction

Model tournaments as versioned directed structures rather than only round numbers:

| Concept | Purpose |
|---|---|
| Tournament | Connects one competition and division to a format and frozen rule version |
| Bracket version | Preserves each preview/publication/corrected structure and generation algorithm version |
| Bracket node | Represents a contest, BYE resolution, final, inactive/active/not-required reset final, third-place playoff, or round-robin fixture |
| Bracket slot | Receives a direct entry or the winner/loser of another node |
| Advancement rule | Routes an approved winner, approved loser, or automatic BYE resolution |
| Draw record | Preserves Admin-entered draw order, source, confirmation actor, and timestamp |
| Node resolution | Records inactive, active, not-required, pending, automatic, submitted, approved, corrected, voided, or ruling-based resolution separately from immutable topology |

Edges must identify source node, source result kind (`winner` or `loser`), target node, and target slot. Foreign keys and domain validation must prevent cycles, cross-tournament routing, duplicate slot occupancy, and a node feeding itself. Exact tables are finalized in the Basketball tracer; double-elimination structures are added only after single elimination is proven.

### Public Sanitization

Public bracket payloads may include event, competition, division, published bracket version, delegation display names, public entry labels, node status, sanitized scores, schedule, venue, official outcome status, `Unofficial` live label, and last-updated timestamp.

They must exclude student numbers, private roster fields, eligibility notes, Judge drafts, assignment identities unless explicitly approved for public display, internal correction reasons, audit metadata, queued commands, and unpublished previews.

## Invariants

- SYNTIX records but never chooses the approved draw order.
- Generation is deterministic for the same locked entries, draw order, format, rule version, and algorithm version.
- Published bracket nodes and edges are immutable; resolution and slot-population state changes only through the defined workflows.
- A BYE is not a contest, win, loss, forfeit, score, or championship-point event.
- No BYE-versus-BYE node exists.
- Only approved contest outcomes advance or route entries.
- A tied knockout result cannot advance.
- Single elimination generates a third-place playoff whenever two semifinal losers exist.
- Every published double-elimination topology includes an inactive reset node, marks it `not_required` when the undefeated finalist wins the first final, and activates it when that finalist loses.
- Double-elimination 2nd Runner-Up is supplied by the losers-bracket-final loser for signed-off supported sizes with at least four entries.
- No double-elimination size activates without a signed-off complete routing map, including explicit no-loser routing from every BYE.
- Odd round robin uses rest slots, not played BYE contests.
- Match approval changes bracket or internal standings only; it never creates championship points.
- Only approved final Division placement creates championship ledger entries.
- Post-publication changes never silently reseed or overwrite downstream history.
- A Tabulator cannot mutate a contest outside an active assignment.
- Public output includes published sanitized data only.

## Edge and Failure Cases

| Case | Required behavior |
|---|---|
| Entry withdraws before publication | Remove from eligible pool and regenerate preview |
| Entry withdraws after publication | Preserve topology, record withdrawal, and do not silently reseed |
| Opponent withdraws before contest | Require authorized confirmation before walkover advancement |
| Entry does not appear | Record no-show or forfeit only after official confirmation |
| Both sides withdraw | Leave node unresolved and require Admin-recorded ruling |
| Late entry before publication | Admin may add it, relock entries, and regenerate preview |
| Late entry after publication | Block unless bracket is formally unpublished before any contest starts |
| Duplicate entry | Reject generation |
| Ineligible entry | Exclude from locked eligible pool |
| Tied knockout result | Block advancement pending official resolution |
| Disqualification during contest | Record approved DQ or forfeit outcome and route accordingly |
| BYE followed by withdrawal | Require Admin ruling before another entry advances |
| Competition cancelled | Preserve bracket history and reverse any official point effects |
| Venue or schedule change | Update scheduling without topology change |
| Tabulator assignment revoked | Reject new mutations immediately and reauthorize queued commands |
| Offline stale result | Reject stale revision and require conflict review |
| Both results submitted concurrently | Use expected revision and idempotency; accept only valid authoritative transition |
| Generation request replayed | Return original generated structure for the same idempotency key |
| Unpublish requested after contest start | Reject; use correction workflow |
| Correction affects live, submitted, or approved descendant | Block approval until an authorized resolution is chosen |
| Correction affects only unstarted descendants without submissions | On corrected-outcome approval, permit safe replacement and audit every changed slot |
| Correction remains draft or submitted | Show impact preview only; do not update any descendant |
| Corrected official outcome approval fails | Roll back the corrected revision and every descendant update |
| Final placement correction | Reverse and replace ledger entries only if official placement changes |

## Functional Requirements

| ID | Requirement |
|---|---|
| TBR-001 | For every tournament format, Admin shall lock eligible entries and record the organizer-approved draw outcome; proposal draw diagrams shall remain non-normative examples. |
| TBR-002 | The system shall auto-place entries into a deterministic preview without conducting the draw. |
| TBR-003 | For two or more entries, the system shall use the next power of two and calculate exact BYEs. |
| TBR-004 | The system shall block zero-entry generation and require an Admin ruling for one uncontested entry. |
| TBR-005 | The system shall represent BYEs as automatic resolutions without fabricated contests, wins, losses, forfeits, scores, or points. |
| TBR-006 | Admin shall review and publish a bracket, after which its version is immutable. |
| TBR-007 | Single elimination shall route approved winners and generate the confirmed-default third-place playoff whenever two semifinal losers exist, with no generic omission setting. |
| TBR-008 | Double elimination shall route first-loss entries to the losers bracket and eliminate only after the second approved loss. |
| TBR-009 | Every published double-elimination topology shall include an inactive reset node, mark it `not_required` when unused, and activate it when the undefeated finalist loses the first championship contest. |
| TBR-010 | Round robin shall schedule all required opponents and assign one rest per round for odd entry counts. |
| TBR-011 | Only an approved outcome shall populate downstream winner or loser slots. |
| TBR-012 | Preliminary match approval shall create no delegation championship ledger entry. |
| TBR-013 | Only separate approval of final Division placement shall create championship points exactly once. |
| TBR-014 | The system shall preserve topology and require rulings for post-publication withdrawals, no-shows, walkovers, forfeits, disqualifications, and both-side withdrawals. |
| TBR-015 | The system shall block late entries after publication unless formally unpublished before all contest activity. |
| TBR-016 | The system shall preserve cancelled brackets and void official point effects through reversals. |
| TBR-017 | Corrections shall update descendants only in the transaction approving the corrected official outcome and never while the correction is draft or submitted. |
| TBR-018 | Admin alone shall generate, publish, unpublish when allowed, reseed through a new preview, and authorize topology corrections. |
| TBR-019 | Tabulators shall score only assigned contests and shall not manually advance bracket slots. |
| TBR-020 | Public users shall receive only sanitized published bracket versions. |
| TBR-021 | Bracket generation and result commands shall be idempotent and revision-aware. |
| TBR-022 | The directed structure shall validate routing integrity and preserve draw, version, node, slot, and advancement audit data. |
| TBR-023 | Double elimination shall derive 2nd Runner-Up from the losers-bracket-final loser for every signed-off supported size with at least four entries. |
| TBR-024 | A double-elimination size shall not activate or publish until its complete winner, loser, placement, reset, and BYE routing map has institutional sign-off. |

## Acceptance Criteria

- Three entries produce a four-slot structure with one played opening node and one BYE resolution.
- Five entries produce an eight-slot structure with three BYEs and no BYE-versus-BYE node.
- Seven Basketball entries produce an eight-slot bracket with one BYE.
- Eight entries produce four played opening nodes and no BYEs.
- Repeating preview generation with identical inputs and algorithm version yields the same nodes, slots, and edges.
- Repeating generation with the same idempotency key creates no duplicate version.
- One entry becomes uncontested and receives no automatic Champion placement or points.
- A BYE advances the expected entry but creates no contest result or ledger entry.
- A submitted but unapproved result leaves the next node empty.
- Approved semifinal outcomes populate the final and, when two losers exist, the third-place playoff.
- A tied knockout result cannot populate a downstream slot.
- The first approved loss routes a double-elimination entry without eliminating it; the second eliminates it.
- A published double-elimination bracket contains the inactive reset node before either finalist is known; an undefeated-finalist win marks it `not_required`, and an undefeated-finalist loss activates it every time.
- The signed-off losers-bracket-final route supplies 2nd Runner-Up, and no BYE sends a phantom loser into that route.
- Odd round-robin schedules contain one rest per round and no fake BYE result.
- A post-publication withdrawal leaves original slots intact and requires the applicable ruling.
- A draft or submitted correction identifies impacted descendants without changing them; approval changes the corrected official outcome and all permitted descendants atomically.
- An unassigned Tabulator is denied; an assigned Tabulator still cannot manually advance a slot.
- Public bracket payloads contain no private roster, assignment, draft, audit, or correction-reason data.
- Final placement approval creates configured championship points exactly once, while every earlier match creates none.

## Testing

### Generation Tests

- Exhaustive single-elimination node, slot, edge, and BYE assertions for 0 through at least 16 entries, with focused snapshots for 3, 5, 7, and 8.
- Determinism for repeated inputs and distinction when draw order changes.
- Duplicate/ineligible entry rejection and one-entry uncontested behavior.
- Cycle, duplicate-slot, self-edge, and cross-tournament routing rejection.

### Format Tests

- Single-elimination winner routing, final, and third-place playoff.
- Double-elimination winner/loser routing for every signed-off supported bracket size, first and second losses, BYEs with no loser edges, losers-bracket-final 2nd Runner-Up, published inactive reset node, `not_required` transition, and activated reset final.
- Round-robin even and odd schedules, rests, wins/draws/losses, and final placement.
- No championship ledger entries from any match, BYE, rest, or internal standings update.

### Lifecycle and Failure Tests

- Publish immutability, legal pre-start unpublish, and illegal post-start unpublish.
- Withdrawal before/after publication, no-show, walkover, forfeit, late entry, DQ, both-side withdrawal, and cancellation.
- Concurrent approval, duplicate command, stale revision, and revoked assignment.
- Correction impact preview with no draft/submitted mutation, approval with unstarted descendants, blocking or authorized resolution for submitted/live/approved descendants, and full transaction rollback on failure.
- Final placement correction with atomic ledger reversal and replacement.

### Authorization and Public Tests

- Admin versus Judge, assigned Tabulator, unassigned Tabulator, and anonymous access.
- Direct requests cannot bypass assignment or publication checks.
- Public payload snapshot proves sanitization and unofficial status labeling.
- Unpublished previews are inaccessible anonymously.

## Decision Register

Tournament blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 24-34. This specification remains the technical contract for deterministic structures, publication, routing, correction, and public sanitization.
