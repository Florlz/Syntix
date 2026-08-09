# Judged Scoring

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines the judged-competition slice under the [product and domain contract](../cspc-siklab-plan.md). The product contract controls shared terminology, lifecycle, authorization, approval, ledger, privacy, and architecture requirements.

- Confirmed product decisions: Laravel and PostgreSQL are authoritative; Judges and Admins are distinct event roles; scoring access also requires an active assignment; a Competition is an activity family and its Division is the score-bearing unit; only an Admin-approved final Division placement affects championship standings; offline scoring follows stable online scoring.
- Proposal-derived rules: `docs/Approved-2025-Intramurals-Proposal.pdf`, especially pages 17-25, supplies the initial 2025 criterion labels, numeric annotations, score limits where stated, and Division-specific deductions. A printed number must not be treated as both a raw maximum and a weight. These are versioned configuration, not application constants.
- Supporting intent: `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` and `docs/SYNTIX.docx` establish paperless Judge entry, automated calculation, detailed reports, and mobile use. They do not override the repository stack or product contract.
- Unresolved matters are listed in [`../open-decisions.md`](../open-decisions.md). Pageants remain outside the initial product scope.

## Problem

Paper scorecards are slow to collect, easy to misread or lose, and require manual calculations whose source numbers may represent point maxima or percentage weights. A digital replacement must reduce clerical error without double-weighting a value, influencing one Judge with another Judge's work, changing frozen rules, or treating an unapproved calculation as an official outcome or placement.

## Goals

- Let assigned Judges independently draft and submit complete criterion scorecards.
- Validate criterion ranges, required values, and allowed deductions against a frozen rule version.
- Calculate reproducible Judge totals without assuming that every proposal number is a percentage weight, and recommend automatic server-side aggregation once the institutional formula is approved.
- Lock submitted scorecards, approve any contest outcome needed by the Division workflow, and route the separate final Division placement through Admin review.
- Create final Division placement points exactly once after placement approval and preserve corrections and reports.

## Non-Goals

- Automated judging or replacement of the Board of Judges.
- Pageant scoring in the initial release.
- Defining the institution's unresolved multi-Judge aggregation, rounding, or tie-break rules.
- Offline Judge mutation synchronization in this slice; it is specified in `docs/specs/07-offline-synchronization.md`.
- Public scoreboard policy, except for identifying data that must remain private.

## Users and Authorization

- A Judge may read the frozen rules, entries, and only that Judge's scorecards for Divisions or contests covered by both an active Judge event role and an active scoring assignment.
- A Judge may create and update only their own draft scorecard. A Judge cannot read peer drafts or submitted peer values through any page, endpoint, broadcast, export, or cache before submitting their own scorecard.
- Submission does not grant rule, placement, approval, ledger, or correction authority.
- An Admin may configure rules before scoring, review all submitted scorecards, reject a scorecard or submission with a reason, approve an Official Contest Outcome where applicable, separately approve the final Division placement, and initiate audited correction or void workflows.
- Public and Tabulator users cannot access Judge drafts, private scorecards, internal deductions, or review notes unless a separately approved sanitized publication policy explicitly permits an official breakdown.
- Policies must enforce role and assignment checks server-side on every read and mutation.

## Workflow

1. Admin configures the Competition family and score-bearing Division, entries, required Judges, exact criterion labels, raw ranges, each proposal number's `maximum` or `weight` meaning, precision, deduction stage, aggregation, tie rule, verified total, and final placement-point rules.
2. The rule version is validated and freezes when scoring starts or the first score is recorded.
3. An assigned Judge opens an entry and receives the frozen scorecard definition without peer scores.
4. The Judge records criterion values and permitted deductions as a private draft.
5. The server validates and recalculates the total on each accepted save using the frozen criterion calculation mode; client calculations are previews only.
6. The Judge reviews and submits a complete scorecard. Submission stores a locked snapshot.
7. When all required scorecards are submitted, the server applies the configured aggregation method and produces a result submission with a calculated contest outcome and Division placement candidate, or blocks if the method or a tie is unresolved.
8. Admin reviews source scorecards, deductions, calculations, completeness, and ties, then approves or rejects the Official Contest Outcome where the workflow requires one.
9. Admin separately reviews and approves the final Division placement. Placement approval atomically records the placement and its championship-point ledger entries under the frozen point-rule version; contest-outcome approval alone creates none.
10. A later correction preserves prior revisions and uses reversal and replacement ledger entries. It never edits an approved scorecard or ledger effect in place.

## Domain/Data Requirements

- A judged rule version must identify the Competition family, score-bearing Division, exact source label for every criterion, criterion order, raw minimum and maximum, whether each proposal number means `maximum` or `weight`, required status, input and calculation precision, deduction definitions and application stage, required Judge count or assignments, scorecard calculation method, Judge aggregation method, rounding policy, tie rule, verified scorecard total, and placement-point version.
- A percentage-weighted rule must total exactly 100 percent. A point-maximum rule must instead reconcile criterion maxima to its verified scorecard total. A criterion number cannot be applied as both maximum points and a percentage multiplier.
- Rule activation requires an independently entered verified total that equals the total produced by the configured calculation before deductions at their configured stage.
- A scorecard must identify event, Competition, Division, contest where applicable, entry, Judge, assignment, frozen rule version, state, revision, draft timestamps, submission timestamp, and submitting actor.
- Each score value must identify its criterion, raw value, criterion number meaning, calculated contribution, and server calculation inputs.
- Each deduction must identify its configured type, amount or formula, reason or evidence reference when required, applying actor, and exact application stage: per criterion, per Judge total, before Judge aggregation, or after Judge aggregation.
- Calculations must retain unrounded intermediate values at an approved fixed precision. Input precision, calculation precision, display total, comparison total, rounding mode, and rounding stage must be explicit once approved.
- A result submission must reference all source scorecard revisions, aggregation configuration, aggregate calculation, resolved tie evidence, calculated Division placement candidates, and current server revision. An Official Contest Outcome approval, when used, is recorded separately from final Division placement approval.
- Final placement points come from the frozen Division point-rule version. Judged Divisions may use different templates; values are not hard-coded by the scoring service.
- The detailed report must include Competition, Division, rule version, exact criteria and number meanings, each authorized Judge's criterion values, deductions and stages, calculated totals, aggregation steps, final placement, separate outcome and placement approval metadata, and correction history.
- The 2025 Essay Writing/Pagsulat ng Sanaysay rule is blocked because its displayed criteria sum to 95 percent despite a printed total of 100 percent. The 2025 Cheer Dance rule is blocked because the displayed `Overall impact` value makes its criterion total invalid. All 2025 Dance Sports Divisions are blocked because the proposal lists criteria without weights. None may activate until corrected institutional values and verified totals are recorded.
- Audit records must identify actor, action, target, event, timestamp, revision, and reason where required.

## Invariants

- A Judge never sees a peer scorecard before submitting their own scorecard.
- A Judge can mutate only their own scorecard under an active assignment.
- Raw values remain within frozen criterion limits, and configured numbers satisfy the selected maximum-point or percentage-weight calculation mode and verified total.
- Submitted scorecards are read-only unless an Admin rejection explicitly returns them for a new corrected revision.
- A result cannot be submitted or approved until every required scorecard is submitted and every required criterion is present.
- The server, not the browser, is authoritative for criterion calculation, deductions, aggregation, ranking, and rounding.
- An unresolved calculation mode, deduction stage, aggregation rule, verified total, or tie blocks activation or placement approval as applicable.
- Approval of an Official Contest Outcome never creates championship ledger entries; only separate approval of the final Division placement does.
- At any cutoff, each delegation's effective championship total is `SUM(amount)` over every committed signed ledger entry at or before that cutoff, including awards, reversals, and replacements; no ledger-status filter applies.
- Approval, replay, correction, and void operations are transactional and idempotent.
- Approved data is preserved; corrections append revisions and compensating ledger entries.

## Edge and Failure Cases

- Assignment revoked while editing: reject the next save or submit, preserve no unauthorized server mutation, and show that access was revoked.
- Session expires: require authentication again without treating an unsent browser value as saved.
- Two devices edit one draft: reject the stale expected revision and require reload; do not merge silently.
- Judge submits twice: return the original submission receipt without creating another scorecard revision.
- Missing or out-of-range value: identify the criterion and keep the scorecard in draft.
- Missing required Judge: block result aggregation and show the outstanding assignment or submission.
- Deduction would produce a negative score: apply the frozen rule's floor if one exists; otherwise block pending an authorized ruling.
- Criterion number meaning, raw range, precision, deduction stage, verified total, aggregation, rounding, or tie rule is not configured: block activation or result submission rather than infer a formula.
- A proposal percentage is entered as both a raw maximum and a weight: reject the rule because it would double-weight the Judge value.
- Essay Writing/Pagsulat ng Sanaysay, Cheer Dance, or Dance Sports uses the uncorrected 2025 source rule: block activation with the recorded source contradiction.
- Exact aggregate tie: apply only the frozen tie-break rule; otherwise require an authorized recorded resolution.
- Rejected scorecard: preserve the submitted revision and rejection reason, then open a new editable revision for the same Judge.
- Approved-result correction: preserve the original report inputs and ledger effect, then create a replacement revision and compensating entries.
- Connection loss in this slice: keep clearly unsaved local form state only as normal browser state; do not claim durable offline submission.

## Functional Requirements

| ID | Requirement |
|---|---|
| JSC-FR-001 | Admin can configure versioned criteria as either point maxima or percentage weights, with exact labels, raw ranges, precision, and a verified total; the system prevents double-weighting. |
| JSC-FR-002 | The system limits Judge access to active role and assignment scope. |
| JSC-FR-003 | Judge can create and revise a private scorecard draft for each assigned entry. |
| JSC-FR-004 | The system prevents a Judge from seeing peer scorecards before that Judge submits. |
| JSC-FR-005 | The server validates criterion completeness, limits, precision, number meaning, deduction stage, and allowed deductions. |
| JSC-FR-006 | The server calculates and stores reproducible criterion contributions and Judge totals using the frozen point-maximum or percentage-weight method. |
| JSC-FR-007 | Judge can submit a complete scorecard and receive a locked, idempotent submission receipt. |
| JSC-FR-008 | The system blocks result aggregation until all required scorecards are submitted. |
| JSC-FR-009 | The server aggregates scorecards using the frozen institutional method and rounding policy once approved. |
| JSC-FR-010 | The system blocks unresolved ties or missing aggregation configuration from approval. |
| JSC-FR-011 | Admin can review source scorecards, calculations, deductions, Official Contest Outcome, and final Division placement before their separate approvals. |
| JSC-FR-012 | Admin can reject a scorecard, outcome submission, or placement submission with an auditable reason and permit correction by revision. |
| JSC-FR-013 | Separate Admin approval of the final Division placement atomically creates the placement and eligible championship-point ledger entries exactly once; contest-outcome approval creates none. |
| JSC-FR-014 | Corrections and voids preserve prior revisions and use append-only ledger reversals and replacements. |
| JSC-FR-015 | Admin can generate a detailed, reproducible Judge score-breakdown report. |
| JSC-FR-016 | The judged-scoring UI states clearly that durable offline scoring is not available in this slice. |
| JSC-FR-017 | The system blocks uncorrected 2025 Essay Writing/Pagsulat ng Sanaysay, Cheer Dance, and Dance Sports rule versions from activation. |

## Acceptance Criteria

- A Judge with one assigned Division can score that Division and receives an authorization denial for another Division in the same Competition family or event.
- Two Judges can save drafts for the same entry without either seeing the other's draft or values.
- An out-of-range or incomplete scorecard cannot be submitted.
- A valid scorecard total is recalculated by the server from the frozen criteria, number meanings, and deduction stages without applying a proposal number twice.
- Replaying a submit request returns the original receipt and does not duplicate the scorecard.
- A submitted scorecard cannot be edited until an Admin rejection creates a correction path.
- Missing required scorecards, unresolved criterion semantics, a mismatched verified total, unresolved aggregation configuration, or unresolved ties block final approval.
- Approving a contest outcome can make it official without creating championship points; separately approving the final Division placement creates each eligible championship point effect exactly once.
- The uncorrected 2025 Essay Writing/Pagsulat ng Sanaysay 95-percent criteria, Cheer Dance invalid total, and Dance Sports criteria without weights each fail activation.
- A correction retains the original scorecards, official revision, approval, and report while appending the replacement effects.
- The detailed report can trace every displayed total to exact criterion labels, raw values, maximum-or-weight meanings, precision, deduction stages, aggregation, tie rule, verified total, rounding, and the frozen rule version.

## Testing

- Domain tests for point-maximum and percentage-weight modes, prevention of double-weighting, verified totals, ranges, deduction stages, negative totals, intermediate precision, aggregation strategies once approved, rounding stages, ranking, and ties.
- Feature tests for event role plus assignment scope, peer-score isolation across pages and endpoints, revocation, submission locking, rejection, resubmission, separate outcome and placement approval, and correction.
- PostgreSQL tests for concurrent draft revisions, duplicate submissions, separate outcome and placement approvals, atomic placement approval and ledger creation, and correction transactions.
- Security tests for direct-object access, private broadcasts, report authorization, cache separation, and absence of peer values in serialized responses.
- Browser tests at mobile and desktop widths for draft, validation, review, locked, rejected, session-expired, stale-revision, and offline-warning states.
- User acceptance simulation with Admins and Judges using at least one proposal-derived literary or musical criterion set and deduction rule.

## Decision Register

Judged-scoring blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 11-18. This specification remains the technical contract for scorecard isolation, criteria validation, aggregation, deductions, and approval.
