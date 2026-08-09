---
name: domain-modeling
description: Use when changing important domain concepts, relationships, states, or invariants; sharpens the model and shared vocabulary before migrations and code.
---

# Domain Modeling

Actively maintain a shared language for the product domain. Challenge ambiguous
terms, define invariants, and record durable decisions before changing data or
application behavior.

## First Read

Read:

- Existing plans, PRDs, design documents, glossaries, and ADRs under `docs/`
- Relevant migrations, models, policies, services, and tests

Create or update these documents when the decision is durable:

- `docs/domain-glossary.md`
- `docs/adr/NNNN-<decision>.md`

## Questions to Resolve

- What nouns do users use for the core concepts?
- Which concepts are global and which are scoped to a parent or lifecycle?
- What states and transitions are valid?
- Which invariants must hold even under retries or concurrent requests?
- Which records can be edited, corrected, superseded, or deleted?
- Which values are authoritative and which are derived views?
- Which information is public, private, or sensitive?

## Modeling Process

1. Write the user-visible workflow in domain language.
2. List nouns, roles, states, transitions, and invariants.
3. Identify terms that mean different things to roles or user groups.
4. Compare at least two model options when a relationship is ambiguous.
5. Choose the simplest model that supports the approved workflows.
6. Record the decision and its rejected alternatives.
7. Add or update tests for the important invariants.

Do not introduce polymorphism, generic JSON, or denormalized totals merely to
avoid making a clear domain decision. Use structured JSON only where the
activity-specific payload genuinely varies and validate it at the domain
boundary.

## Project Context

For the current domain vocabulary, read `docs/cspc-siklab-plan.md`. Terms such
as event, delegation, activity, registration, result, score ledger, and
standings are project terms, not universal rules of this skill.
