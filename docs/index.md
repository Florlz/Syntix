# SYNTIX Documentation

This index is the entry point for project documentation. Keep product rules, implementation status, architectural decisions, technical contracts, and unresolved institutional questions in separate canonical documents.

## Reading Order

1. [`../README.md`](../README.md) for setup and repository-level orientation.
2. [`cspc-siklab-plan.md`](cspc-siklab-plan.md) for product scope, domain boundaries, and invariants.
3. [`domain-glossary.md`](domain-glossary.md) for shared vocabulary.
4. [`implementation-status.md`](implementation-status.md) for what exists, what is partial, and what comes next.
5. [`open-decisions.md`](open-decisions.md) for institutional decisions that block activation or publication.
6. The relevant ADR and focused specification before changing a domain slice.

## Source Of Truth

| Question | Canonical location |
|---|---|
| What is SYNTIX and what is in scope? | [`cspc-siklab-plan.md`](cspc-siklab-plan.md) |
| What does a domain term mean? | [`domain-glossary.md`](domain-glossary.md) |
| What has been implemented? | [`implementation-status.md`](implementation-status.md) |
| Which institutional rule is unresolved? | [`open-decisions.md`](open-decisions.md) |
| Why was an architectural choice made? | [`adr/`](adr/) |
| How should one capability behave technically? | [`specs/`](specs/) |
| What did the source institution publish? | Source artifacts listed below |

## Architectural Decisions

- [`ADR 0001: Authoritative stack`](adr/0001-authoritative-stack.md)
- [`ADR 0002: Event roles and assignments`](adr/0002-event-roles-and-assignments.md)
- [`ADR 0003: Results, placements, and ledger`](adr/0003-results-placements-and-ledger.md)
- [`ADR 0004: Offline scoring commands`](adr/0004-offline-scoring-commands.md)
- [`ADR 0005: Tournament bracketing`](adr/0005-tournament-bracketing.md)

## Focused Specifications

- [`01 Identity and RBAC`](specs/01-identity-and-rbac.md)
- [`02 Competition scoring rules`](specs/02-competition-scoring-rules.md)
- [`03 Tournament bracketing`](specs/03-tournament-bracketing.md)
- [`04 Basketball tracer`](specs/04-basketball-tracer.md)
- [`05 Judged scoring`](specs/05-judged-scoring.md)
- [`06 Athletics aggregation`](specs/06-athletics-aggregation.md)
- [`07 Offline synchronization`](specs/07-offline-synchronization.md)
- [`08 Admin frontend`](specs/08-admin-frontend.md)
- [`09 Public scoreboard`](specs/09-public-scoreboard.md)
- [`10 Reports and archive`](specs/10-reports-and-archive.md)

## Source Artifacts

These files preserve the institutional material used to derive the product contract. They are references, not executable requirements; the product contract and recorded decisions resolve conflicts.

- [`Approved-2025-Intramurals-Proposal.pdf`](Approved-2025-Intramurals-Proposal.pdf)
- [`MANUSCRIPT FINAL 123 PDF COPY.pdf`](MANUSCRIPT%20FINAL%20123%20PDF%20COPY.pdf)
- [`SYNTIX.docx`](SYNTIX.docx)

## Documentation Rules

- Do not create dated plans for work that is already represented in `implementation-status.md`.
- Put a new architectural choice in an ADR, not in a plan or implementation note.
- Put unresolved institutional rules in `open-decisions.md`; focused specs should link to it rather than maintain competing lists.
- Keep product-wide rules in the product contract and avoid copying them into every specification.
- Keep implementation claims tied to code and verification evidence.
