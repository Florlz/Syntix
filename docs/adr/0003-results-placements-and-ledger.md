# ADR 0003: Results, Placements, And The Score Ledger

## Status

Accepted

## Date

2026-08-09

## Context

SYNTIX supports mutable live scoring, reviewable submissions, tournament advancement, aggregate ranking, and event-level point awards. A single generic result record would blur official status, make retries dangerous, and make corrections destructive.

## Decision

Persist and authorize five separate concepts:

1. Contest state, including mutable Live Score and lifecycle revision.
2. Result Submission, a locked scorer snapshot awaiting Admin review.
3. Official Contest Outcome, the approved outcome of one Contest.
4. Division Placement, the approved final ordering for the Division.
5. Score Ledger Entry, the append-only championship-point effect of an approved final placement.

Competition is the activity family. Division is the score-bearing category, including an explicit default or Open Division when the Competition has no named categories. Division Placement and Point Rule Version therefore always attach to a Division, never directly to a Competition or to an implicit null Division.

An approved preliminary match outcome may advance a bracket or update internal Division Standings, but it never awards Championship Points. Discipline Sub-Points likewise rank an aggregate Division without entering the ledger directly.

Only approval of an eligible final Division Placement under that Division's frozen Point Rule Version creates Championship Points. That source effect is idempotent and awarded exactly once per eligible placement and delegation. Approval and ledger creation occur in one transaction.

Corrections preserve prior submissions, official revisions, and ledger entries. A changed point effect appends compensating reversal and replacement entries; approved records and ledger history are not overwritten or deleted.

Every committed Score Ledger Entry has a signed `amount` and is classified as an award, reversal, or replacement. Awards are positive, reversals are negative, and replacements are positive. The ledger invariant is:

`delegation championship total = SUM(amount)` over all committed entries for that Event and Delegation, including awards, reversals, and replacements.

All committed entries participate in the sum. A correction changes the derived total only through newly committed signed entries.

## Consequences

- Division and overall Standings are derived views, not editable totals.
- Submission approval can produce an Official Contest Outcome without producing ledger entries.
- Final placement approval requires uniqueness and transactional checks against duplicate awards.
- Reports can reproduce the rule version, official revision, and ledger effects used at the time.

## Rejected Alternatives

- One mutable result table for every stage: rejected because unofficial, submitted, official, and corrected facts have different invariants.
- Award points for each match win: rejected because Championship Points come from final Division Placement.
- Write Sub-Points directly to the championship ledger: rejected because they are internal ranking values.
- Recalculate by editing totals or deleting prior awards: rejected because it destroys auditability and retry safety.

## References

- [SYNTIX product and domain contract](../cspc-siklab-plan.md)
- [SYNTIX Domain Glossary](../domain-glossary.md)
