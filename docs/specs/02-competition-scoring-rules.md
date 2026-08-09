# Competition Scoring Rules Specification

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines the scoring and rule-version requirements in the [product and domain contract](../cspc-siklab-plan.md). Identity, bracket topology, and Basketball interaction details remain in their focused specifications.

Source priority follows the product contract:

1. Product-owner decisions recorded in the product contract and decision register.
2. `docs/Approved-2025-Intramurals-Proposal.pdf`, especially pages 3 and 10-25, for official 2025 formats, sub-points, championship points, criteria, and deductions.
3. `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` for automated ranking, weighted-scoring, reporting, and black-box testing objectives.
4. `docs/SYNTIX.docx` for scoring configuration and scorer workflows.
5. The repository for the authoritative implementation stack.

The proposal's pageant point schedule is not included because pageants, including Mr. and Ms. Intramurals, are excluded from the initial product scope.

## Problem

SIKLAB uses several distinct notions of a score: performance inside a contest, sub-points that aggregate disciplines within a score-bearing Division, and championship points awarded for final Division placement. Paper tallying and undifferentiated totals make it easy to award the wrong points, recalculate with changed rules, round inconsistently, or count preliminary wins directly in the overall standings. SYNTIX needs reusable, versioned rules that preserve the approved 2025 schedules while allowing future editions to configure their rules before scoring begins.

## Goals

- Represent proposal-derived Division formats as configuration, not hard-coded event-name branches.
- Keep contest performance, Division sub-points, and delegation championship points separate.
- Seed the four approved 2025 placement templates as versioned data.
- Support Athletics, Arnis, and Taekwondo discipline sub-point aggregation, including Athletics relay values.
- Freeze the exact rule version used once scoring begins.
- Apply penalties and deductions transparently and reproducibly.
- Require deterministic decimal precision and rounding configuration before activation.
- Create championship points only from an approved final score-bearing Division placement.
- Preserve unresolved proposal ambiguities as explicit institutional decisions.

## Non-Goals

- Pageant scoring or the proposal's Mr. and Ms. Intramurals schedule.
- Detailed bracket generation, routing, or Basketball score-entry interaction.
- Defining every sport governing-body rule in one generic scoring engine.
- Automatically deciding protests, referee calls, Judge opinions, ties without configured rules, or institutional policy.
- Manual editing of derived overall standings.
- Medal tracking unless CSPC separately approves it.

## Users and Authorization

- Admin creates Division rule drafts, selects templates, configures scoring, and activates a version before scoring.
- Admin may edit rules only in `draft` or `activated_editable` while the Division has no started contest and no recorded score.
- Judge reads the frozen criteria and deduction rules for assigned criteria-based work and enters only permitted values.
- Tabulator reads the frozen objective-scoring rules for contests covered by active `competition_division` or `contest` assignments and records performance, outcomes, measurements, and placements.
- Admin separately reviews contest outcomes and final Division placements and approves, rejects, corrects, or voids them.
- Judge and Tabulator cannot edit rules, point mappings, official placements, or ledger entries.
- Public reads only sanitized live unofficial performance and approved official placements or standings.
- All scorer access also requires the event role and active assignment defined by `01-identity-and-rbac.md`.

## Workflow

### Rule Configuration

1. Admin creates a canonical Competition family, creates its score-bearing Division or Divisions, and selects each Division's proposal-aligned format and scoring family.
2. Admin starts from a reusable point template or creates a Division-specific draft.
3. Admin configures entry mode, contest scoring, ranking direction, criteria, sub-points if applicable, penalties, tie behavior, participation conditions, and championship placement mapping.
4. The system validates internal consistency and requires decimal precision and rounding configuration where calculations can produce fractions.
5. Admin previews worked examples and moves the version from `draft` to `activated_editable`.
6. The governing `activated_editable` version remains editable only until the first Division contest starts or first score is recorded, whichever occurs first, when it becomes `frozen` automatically.

### Scoring and Placement

1. Assigned scorers record Layer 1 contest performance under the frozen rule version.
2. The system calculates any configured weighted totals, deductions, internal wins, measurements, or discipline placements.
3. For aggregate Divisions, approved discipline placements produce Layer 2 sub-points.
4. A scorer submits a locked contest-result snapshot for Admin review.
5. Contest-outcome approval creates an immutable official contest outcome and may update bracket or internal ranking state, but creates no championship ledger entry.
6. After every required official contest outcome or discipline result exists, the system derives a final Division placement candidate.
7. Admin separately approves the final Division placement and atomically appends its Layer 3 championship ledger entries.

### Correction

1. A post-freeze rule change creates a new version; it never mutates the historical version or silently governs the same live Division.
2. An authorized correction references the original result and rule version, records a reason, and produces a new revision.
3. If the approved final Division placement changes, the system appends signed reversal and replacement ledger entries.
4. Historical reports continue to calculate from the rule version and result revision originally applicable.

## Domain/Data Requirements

### Competition Family and Score-Bearing Division

`Competition` is the canonical event-scoped activity family, such as Basketball, Athletics, or Essay Writing. A `Division` is the score-bearing category within that Competition, such as Men, Women, Mixed, or Open.

- Contests, entries, the governing rule version, final placement, and placement-point award belong to a Division.
- A Competition family does not itself receive a duplicate placement or ledger award.
- A family may have one Division, but the Division record is still required; do not collapse single-Division activities into Competition.
- If source policy combines categories, configure one explicit combined score-bearing Division or an approved aggregate Division. Do not calculate an implicit family-level placement across sibling Divisions.

### Proposal-Derived Competition Formats

The 2025 seed configuration supports these formats on score-bearing Divisions of the named Competition families:

| Format | Proposal-derived Competition families |
|---|---|
| Single elimination | Basketball, Volleyball, Sepak Takraw, Arnis, Taekwondo; Athletics is disputed and must not be forced into this format |
| Double elimination | Badminton, Table Tennis, Lawn Tennis |
| Round robin | Chess |
| Aggregate placement | Athletics, Arnis, and Taekwondo discipline or division results |
| Criteria-based placement | Literary, musical, dance, visual arts, Radio Drama, and other approved judged activities |
| Custom series | Esports |

Athletics detailed rules describe times, distances, heats or finals, relays, and aggregate sub-points. Its proposal table also says single elimination. This conflict remains open and blocks a knockout interpretation.

### 2025 Criteria Appendix and Source Blockers

The 2025 seed package must include a reviewable criteria appendix for every criteria-based Division. Each appendix row records Competition, Division, criterion name, exact weight, score range, deduction rule, aggregation rule, source page, transcription status, reviewer, and approval reference. The appendix is versioned with the rule configuration and is required evidence for activation; prose summaries or an untraceable total are insufficient.

The following source defects block activation of the affected 2025 Division until CSPC supplies corrected, signed-off values:

| Affected configuration | Source defect | Required behavior |
|---|---|---|
| Essay Writing | Displayed criterion weights total 95 percent | Do not normalize or invent the missing 5 percent; block activation |
| Cheer Dance | The displayed Overall Impact value makes the criterion total invalid | Do not rebalance the other criteria; block activation |
| Dance Sports | Official criterion weights are absent | Do not infer equal or customary weights; block activation |

Every other criteria-based 2025 Division must also pass the exact 100-percent appendix validation before activation.

### Three Scoring Layers

#### Layer 1: Contest Performance

Raw operational performance includes match points, periods, sets, rounds, wins, losses, draws, forfeits, Judge criterion values, deductions, times, distances, qualification status, or series scores. It may be publicly displayed while live only as `Unofficial` with a timestamp. Layer 1 never enters the overall championship ledger directly.

#### Layer 2: Division Sub-Points

Athletics, Arnis, and Taekwondo use approved discipline placements to rank delegations within the configured aggregate Division.

| Discipline class | 1st | 2nd | 3rd | Participation |
|---|---:|---:|---:|---:|
| Normal individual discipline, including Arnis and Taekwondo weight divisions | 5 | 4 | 3 | 1 |
| Athletics relay discipline | 10 | 8 | 6 | 2 |

The proposal states that men's and women's category scores are separated in computing scores, while the product contract preserves the unresolved choice until it is approved. The institutional decision must therefore determine whether the 2025 family has separate score-bearing Divisions or one explicitly configured combined aggregate Division. Sub-points determine Division placement but never enter the general championship ledger.

#### Layer 3: Delegation Championship Points

The approved 2025 proposal supplies four initial templates for final Division placements:

| Template | Champion | 1st Runner-Up | 2nd Runner-Up | Participation | Proposal examples |
|---|---:|---:|---:|---:|---|
| Major | 25 | 20 | 15 | 5 | Basketball, Volleyball, Athletics, Arnis, Taekwondo |
| Standard | 20 | 15 | 10 | 5 | Badminton, Sepak Takraw, Table Tennis, Lawn Tennis, Hip-Hop, Folk Dance, Contemporary Dance, Esports, Radio Drama, Quiz Bowl |
| Individual | 5 | 4 | 3 | 1 | Literary, solo musical, and visual arts competitions |
| Intermediate | 8 | 6 | 4 | 2 | Dance Sports, Vocal Duet, Chess |

The source uses `Champion`, `1st Runner-Up`, `2nd Runner-Up`, and `Participation`; store explicit placement keys rather than relying on array position. Only approval of the final Division placement creates these points. Match wins, BYEs, heat qualification, discipline sub-points, and preliminary placement do not.

### Rule Versions

Each Division rule version requires:

- Event, Competition family, score-bearing Division, scoring family, and format.
- Effective lifecycle state: `draft`, `activated_editable`, `frozen`, `superseded`, or `archived`.
- Entry limits and participant mode.
- Layer 1 value types, limits, sort direction, completeness, and outcome behavior.
- Criteria weights and allowed deductions where judged scoring applies.
- Layer 2 discipline classes and placement mappings where aggregation applies.
- Layer 3 placement template and participation eligibility conditions.
- Tie-breaker sequence or an explicit `manual_resolution_required` setting.
- Decimal scale, rounding mode, and rounding stage for every calculated value.
- Publication controls and required approval evidence.
- Creator, activator, timestamps, and relationship to any prior version.

Normalized point mappings, criteria, score values, deductions, placements, result revisions, and ledger entries are preferred. Structured JSON is limited to validated sport-specific metadata that does not justify a normalized relation.

The lifecycle is:

```text
draft -> activated_editable -> frozen -> superseded -> archived
```

- `draft` is not governing and cannot score.
- `activated_editable` is the one governing pre-score version and may be edited only while no contest has started and no score exists.
- `frozen` is immutable and remains bound to every contest, outcome, placement, and ledger effect created under it.
- `superseded` remains immutable and queryable after an explicitly approved replacement takes governance.
- `archived` remains immutable and available for historical reproduction but cannot govern new scoring.
- A Division has at most one governing version. If the Division is already live, activating a later version is blocked unless an authorized, audited correction or migration plan explicitly identifies affected records. The governance switch atomically supersedes the prior version, moves the replacement through `activated_editable` to `frozen`, binds it as governing, and never retroactively changes historical records by default.

### Decimal Precision and Rounding

Binary floating-point is not authoritative. PostgreSQL fixed-precision numeric values or integer minor units must be used.

Every rule version that can produce a fraction must freeze:

- Input scale.
- Intermediate calculation scale.
- Display and final-total scale.
- Rounding mode.
- Whether rounding occurs per criterion, per Judge, per discipline, or only after aggregation.

Activation is blocked when any required precision setting is absent. The exact 2025 precision and rounding sequence are open institutional decisions; the system must not silently choose a rule that can alter placement.

### Penalties and Deductions

A penalty rule records a reason code, trigger or authorized manual basis, value type, amount, applicable scoring layer, application stage, and whether evidence or a note is required.

- Deductions are stored separately from raw scores.
- Totals expose raw amount, each deduction, and net amount.
- Only configured deductions can be entered by a Judge or Tabulator.
- An authorized official remains responsible for deciding whether a real-world violation occurred.
- Correction never overwrites the original deduction; it creates a revision.
- Examples in the proposal include time-limit deductions and safety or rule-violation penalties, but each activity's exact penalty configuration belongs to its own frozen rule version.

### Participation and Ties

The proposal provides participation values but does not define eligibility for withdrawals, no-shows, forfeits, disqualifications, cancelled competitions, or entries that never perform. Participation-point eligibility must therefore be explicit configuration approved by CSPC.

Unresolved ties block final placement approval unless the frozen rule version defines a complete tie-breaker or an authorized human ruling is recorded with reason and authority. The overall championship tie sequence is also open.

## Invariants

- The three scoring layers are stored and calculated separately.
- Layer 1 performance and Layer 2 sub-points never directly create championship ledger entries.
- Contest-outcome approval is distinct from final Division-placement approval and creates no Layer 3 entry.
- Only an approved final Division placement under a frozen point-rule version creates Layer 3 entries.
- The same final placement approval cannot award championship points twice.
- Rules are editable only in `draft` or `activated_editable` before any contest starts or score is recorded.
- A frozen or historically referenced rule version is immutable.
- A later version cannot silently govern a live Division or retroactively replace a record's frozen version.
- Corrections and voids append revisions and ledger reversals; they do not destructively rewrite official history.
- Criterion weights total exactly 100 percent before criteria-based scoring begins.
- Raw scores remain distinct from penalties, deductions, weighted totals, and net totals.
- Decimal scale, rounding mode, and rounding stage are frozen before calculated scoring begins.
- An unresolved tie blocks approval.
- Effective official standings at a cutoff equal `SUM(amount)` across every committed signed ledger entry through that cutoff, including positive awards, negative reversals, and positive replacements; ledger rows have no active/inactive filter and totals cannot be manually overwritten.
- Pageant rules and points are absent from the initial product.

## Edge and Failure Cases

- No entries or one entry: no automatic points; apply the tournament or authorized uncontested ruling workflow.
- Missing required scorecard, measurement, discipline result, or placement: block submission or approval as appropriate.
- Duplicate score or approval command: return the original authoritative result by idempotency key.
- Stale score revision: reject and require conflict review.
- Rule edit after scoring starts: reject; require a new version and an explicitly approved correction or migration plan before any governance change.
- Rule version lacks precision, tie, participation, or mapping data: block activation or final approval where that data is required.
- Criteria weights do not total 100 percent: block activation.
- Deduction would exceed configured bounds or is not authorized for the scorer: reject it.
- Net total becomes negative: follow the frozen rule's floor behavior; block activation if floor behavior is unspecified.
- Equal aggregate sub-points: apply the frozen aggregate tie-breaker or block approval.
- Division aggregation policy is unresolved: do not merge men's and women's points by assumption.
- Participant withdraws, forfeits, no-shows, or is disqualified: record the status, but do not infer participation points until policy says the status qualifies.
- Division is cancelled or unplayed: preserve records and award no points unless an authorized final ruling explicitly says otherwise under an approved policy.
- Corrected final Division placement: append negative reversals and positive replacements atomically without deactivating prior ledger rows.
- Public display receives a live total: label it unofficial and exclude private Judge drafts and deduction reasons not approved for publication.

## Functional Requirements

| ID | Requirement |
|---|---|
| SCR-001 | Admin shall configure each score-bearing Division of a canonical Competition family from a proposal-aligned format and scoring family before scoring begins. |
| SCR-002 | The system shall seed Major 25/20/15/5, Standard 20/15/10/5, Individual 5/4/3/1, and Intermediate 8/6/4/2 as versioned 2025 templates. |
| SCR-003 | The system shall keep contest performance, Division sub-points, and delegation championship points as separate scoring layers. |
| SCR-004 | The system shall support normal discipline sub-points of 5/4/3/1 for Athletics, Arnis, and Taekwondo. |
| SCR-005 | The system shall support Athletics relay sub-points of 10/8/6/2. |
| SCR-006 | The system shall aggregate only approved eligible discipline placements into Division sub-points. |
| SCR-007 | Admin shall edit a rule version only in `draft` or `activated_editable` before the first contest starts or first score is recorded. |
| SCR-008 | The system shall freeze the governing rule version when scoring starts and preserve it for historical results and reports. |
| SCR-009 | A later rule change shall create a new version and shall not govern the same live Division without an explicit audited correction or migration workflow. |
| SCR-010 | Every fractional scoring rule shall define fixed precision, rounding mode, and rounding stage before activation. |
| SCR-011 | The system shall store raw values, deductions, and net calculated values separately. |
| SCR-012 | The system shall allow only configured and authorized penalties or deductions. |
| SCR-013 | The system shall block final approval when a tie is unresolved under the frozen rule. |
| SCR-014 | Participation points shall be awarded only when the entry satisfies the institutionally approved status conditions in the frozen rule. |
| SCR-015 | Contest-outcome approval and Layer 2 aggregation shall create no delegation championship points. |
| SCR-016 | Separate approval of an eligible final Division placement shall create signed Layer 3 ledger entries atomically and exactly once. |
| SCR-017 | Corrections and voids shall preserve history through result revisions and append-only ledger reversals. |
| SCR-018 | Public live values shall be sanitized, timestamped, and labeled unofficial until approval. |
| SCR-019 | The initial product shall exclude pageant scoring and pageant point schedules. |
| SCR-020 | Official standings at a cutoff shall equal the sum of every committed signed ledger amount through that cutoff, without active-ledger filtering or direct total editing. |
| SCR-021 | The model shall use canonical Competition families and required score-bearing Divisions; placements and ledger awards shall attach only to Divisions. |
| SCR-022 | Rule versions shall follow `draft -> activated_editable -> frozen -> superseded -> archived` and enforce the permissions of each state. |
| SCR-023 | Every 2025 criteria-based Division shall have the source-traceable criteria appendix defined here and shall total exactly 100 percent before activation. |
| SCR-024 | Essay Writing, Cheer Dance, and Dance Sports 2025 rule versions shall remain blocked until their invalid or missing criterion weights receive institutional sign-off. |

## Acceptance Criteria

- The four templates reproduce 25/20/15/5, 20/15/10/5, 5/4/3/1, and 8/6/4/2 exactly without event-name conditionals.
- An Athletics normal discipline placement of first through participation yields 5, 4, 3, and 1 sub-points; a relay yields 10, 8, 6, and 2.
- Arnis and Taekwondo approved discipline placements use 5/4/3/1 and affect only aggregate Division ranking.
- A Basketball semifinal win advances internal Division state but creates no championship ledger entry.
- Approval of final Major Division placements creates 25, 20, 15, and any eligible participation awards exactly once.
- Editing a draft rule before scoring succeeds; editing it after a contest starts or score exists fails.
- An activated but unstarted rule is editable; first activity freezes it, and each lifecycle transition preserves a unique audit trail.
- A later version cannot replace the governing version of a live Division without the explicit approved switch, and existing outcomes retain their original frozen version.
- A historical result and report continue using their frozen version after a new version is created.
- A calculated scoring rule without precision and rounding settings cannot activate.
- Raw score, deduction, and net score remain independently visible in authorized review and reproducible in a report.
- An unresolved tie or missing required discipline result blocks final approval.
- Duplicate approval does not duplicate ledger effects.
- Correcting final placement preserves the original result and appends balancing reversal and replacement entries.
- Standings include every committed positive and negative ledger row and equal their signed sum without an `active` predicate.
- The 2025 criteria appendix traces each criterion to a source page; Essay Writing at 95 percent, invalid Cheer Dance totals, and missing Dance Sports weights all fail activation.
- No pageant template, competition scoring path, or points enter the initial seed scope.

## Testing

### Domain Tests

- Exact placement mappings for all four templates.
- Normal and relay sub-point mappings.
- Multi-discipline aggregation with men's and women's data kept according to the selected institutional policy.
- Fixed-precision arithmetic at boundary values and every configured rounding stage.
- Weighted criteria totaling and invalid weight sums.
- Penalty application, limits, negative-total floor behavior, and correction revisions.
- Tie-breaker completion and unresolved-tie rejection.
- Participation eligibility for each institutionally approved result status.
- Final-placement-to-ledger conversion and no conversion from Layers 1 or 2.
- Signed ledger SUM behavior across awards, reversals, replacements, and report cutoffs.

### Feature and Integration Tests

- Admin edit in draft and activated-editable states and denial after freeze.
- Every rule lifecycle transition, single-governing-version enforcement, blocked live-Division replacement, and approved atomic supersession.
- Criteria appendix completeness, source traceability, exact 100-percent totals, and the three known 2025 activation blockers.
- Assigned Judge and Tabulator access versus unauthorized rule mutation.
- Transactional, idempotent approval and duplicate-command handling.
- PostgreSQL row locking during simultaneous approval or correction.
- Append-only reversal and replacement entries.
- Public unofficial labeling and private data exclusion.
- Historical report reproduction from frozen versions.

### Black-Box and User Acceptance Tests

- Run representative team sport, timed event, distance event, criteria event, and aggregate event from entry through approval.
- Have official tabulators independently calculate sample 2025 totals and compare every layer with SYNTIX.
- Include missing data, ties, deductions, withdrawals, and corrected placements.
- Record expected result, observed result, discrepancy, and institutional sign-off.

## Decision Register

Scoring and rule blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 9-23. This specification remains the technical contract for versioned criteria, precision, ranking, and point rules after those decisions are recorded.
