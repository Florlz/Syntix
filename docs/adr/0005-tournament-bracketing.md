# ADR 0005: Tournament Bracketing

## Status

Accepted

## Date

2026-08-09

## Context

The approved proposal supplies competition-format and drawing examples for named activities, but it does not establish a universal draw-entry policy. A confirmed product decision requires Admin-entered Draw Order for every Tournament. SYNTIX must construct auditable tournament structures without replacing organizer judgment or fabricating match outcomes.

## Decision

Use the proposal-aligned initial formats by Competition family: single elimination for Basketball, Volleyball, Sepak Takraw, Arnis, and Taekwondo; double elimination for Badminton, Table Tennis, and Lawn Tennis; round robin for Chess; aggregate placement for Athletics; criteria-based placement for judged competitions; and configured custom series for Esports. Each format instance belongs to an explicit Division, including a default or Open Division where the Competition has no named categories. Athletics remains an institutional clarification item and is not forced into a knockout bracket.

Under the confirmed product decision, an Admin records the authorized Draw Order for every Tournament. The order may come from a manual draw or approved Seeds, but SYNTIX never chooses it. For the same locked eligible entries, Draw Order, format, and rule version, generation is deterministic. For two or more eligible entries, bracket size is the next power of two and the difference is resolved as auditable BYEs, never BYE-versus-BYE contests. A sole eligible entry creates an Uncontested Division requiring an authorized ruling; it is not automatically champion.

Single-elimination tournaments include a mandatory third-place playoff between semifinal losers. The final determines Champion and 1st Runner-Up; the playoff determines 2nd Runner-Up.

Double elimination requires two approved losses to eliminate an entry. Its published topology always contains the first grand final and a reset-final node initially marked `inactive`. If the undefeated finalist wins the first grand final, the reset final becomes `not_required`. If the undefeated finalist takes a first loss, the reset final becomes `active` and determines Champion and 1st Runner-Up. This state transition does not alter the published topology.

For double elimination, 2nd Runner-Up is the entry eliminated immediately before the two grand finalists, as identified by the signed-off routing map for that format and bracket size. Each supported format-size pair must have a signed-off routing table defining every winner route, loser route, BYE resolution, grand-final input, reset-final transition, and placement extraction before that pair can be activated in production. Generation is not enabled for an unapproved size.

Only an approved Official Contest Outcome can advance winner or loser routes. Live, completed, or submitted state cannot advance a bracket. BYEs resolve automatically without a Contest outcome; Walkovers and Forfeits require official confirmation.

Bracket previews may be regenerated before publication. Publication creates an immutable Bracket Version containing directed Bracket Nodes, Bracket Slots, Advancement Rules, Draw Order, and generation inputs. Later structural correction creates an audited replacement version, preserves prior versions, detects affected descendants, and never silently reseeds or rewrites started or approved contests.

## Consequences

- Schedule and venue edits do not alter bracket topology.
- Post-publication withdrawals remain visible and require the configured Walkover, Forfeit, or Admin-ruling workflow.
- Bracket generation and publication are idempotent, authorized Admin commands.
- Preliminary advancement does not create Championship Points; only approved final Division Placement can do so.
- Double-elimination loser routing, 2nd Runner-Up extraction, and reset states require deterministic tests against every signed-off routing table.

## Rejected Alternatives

- System-selected random draws: rejected because organizer-approved drawing or seeding is authoritative.
- Fixed bracket sizes or ad hoc empty matches: rejected because next-power-of-two BYEs are deterministic and auditable.
- Automatic championship for a sole entry: rejected because institutional participation and point eligibility require a ruling.
- Derive third place from semifinal loss order: rejected because the plan requires a third-place playoff.
- Omit the reset final until needed: rejected because published double-elimination topology must remain immutable while the node changes from `inactive` to `active` or `not_required`.
- Activate a bracket size without a signed-off routing table: rejected because generic routing assumptions can misroute losers or assign the wrong final placement.
- Advance from live or submitted data: rejected because only approved outcomes are official.
- Mutate a published bracket in place: rejected because it hides the structure used by historical outcomes.

## References

- [SYNTIX product and domain contract](../cspc-siklab-plan.md)
- `docs/Approved-2025-Intramurals-Proposal.pdf`, especially sports formats and tournament rules.
- [SYNTIX Domain Glossary](../domain-glossary.md)
